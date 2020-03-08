#!/usr/bin/php
<?php
// Count disk usage on mounted shares on given host.
// Tomasz Klim, Jan 2012, Feb 2016


$ignore_mounts = array (
	"/boot",
	"/backup",
);

// only count these shares in --force mode, to prevent waking up underlying
// devices from standby mode (see sf-standby-monitor extension for details)
$less_often_reports = array (
	"/srv/mounts/go",
	"/srv/mounts/external1",
	"/srv/mounts/external2",
	"/srv/mounts/external3",
	"/srv/mounts/external4",
	"/srv/mounts/elements1",
	"/srv/mounts/elements2",
	"/srv/mounts/elements3",
	"/srv/mounts/elements4",
	"/srv/mounts/elements5",
	"/srv/mounts/elements6",
	"/srv/mounts/elements7",
	"/srv/mounts/elements8",
);



class Executor
{
	public static $host = false;
	public static $user = false;
	public static $port = false;
	public static $key = false;

	public static function execute($command)
	{
		if (self::$host) {
			$host = self::$host;
			$user = self::$user;
			$port = self::$port;
			$key = self::$key;
			$str = shell_exec("ssh -i $key -p $port -o StrictHostKeyChecking=no $user@$host \"$command\"");
		} else {
			$str = shell_exec($command);
		}

		return explode("\n", trim($str));
	}
}


class Capacity
{
	protected $df = array();

	public function __construct()
	{
		$arr = Executor::execute("df -PB 1");

		foreach ($arr as $line) {
			$columns = preg_split("/ /", $line, -1, PREG_SPLIT_NO_EMPTY);
			$this->df[$columns[0]] = array (
				"capacity"  => $columns[1],
				"used"      => $columns[2],
				"available" => $columns[3],
				"percent"   => $columns[4],
				"path"      => $columns[5],
			);
		}
	}

	public function get($fs, $mp, $mode)
	{
		if (isset($this->df[$fs][$mode]))
			return $this->df[$fs][$mode];
		else if (isset($this->df["/dev/$fs"][$mode]))
			return $this->df["/dev/$fs"][$mode];
		else
			foreach ($this->df as $index => $data)
				if ($mp == $data["path"])
					return $this->df[$index][$mode];
		return "";
	}
}


function prepare_json_array($data)
{
	$from = array(",",   "{",   "}"  );
	$to   = array(",\n", "{\n", "\n}");
	return str_replace($from, $to, json_encode($data));
}


function read_json_array($file)
{
	$data = file_get_contents($file);

	if ($data)
		return json_decode($data, true);
	else
		die("warning: file $file not found\n");
}


function list_mounts($type)
{
	$arr = Executor::execute("mount |grep '$type'");
	$out = array();

	foreach ($arr as $line) {
		if (!empty($line)) {
			$columns = explode(" ", $line);
			$out[$columns[0]] = $columns[2];
		}
	}

	return $out;
}


function get_directory_usage($path, $expand_directories)
{
	$tmp = get_entries($path);
	$out = array();

	foreach ($tmp as $entry => $size) {
		if (!isset($expand_directories[$entry])) {
			$out[$entry] = $size;
		} else {
			$tmp2 = get_entries("$path/$entry");
			foreach ($tmp2 as $entry2 => $size2) {
				$alias = $expand_directories[$entry];
				$out["$alias/$entry2"] = $size2;
			}
		}
	}

	return $out;
}


function get_excludes($path)
{
	if ($path == "/")
		$exclude = array("lost+found", "boot", "dev", "proc", "run", "selinux", "sys", "vmlinuz", "vmlinuz.old", "initrd.img", "initrd.img.old");
	else
		$exclude = array("lost+found");

	$out = "";

	foreach ($exclude as $entry)
		$out .= " --exclude=$entry";

	return $out;
}


function get_entries($path)
{
	$excludes = get_excludes($path);
	$arr = Executor::execute("du $excludes -bs $path/* 2>/dev/null");
	$out = array();

	foreach ($arr as $line) {
		$columns = explode("\t", $line);
		$subject = basename($columns[1]);
		$out[$subject] = $columns[0];
	}

	return $out;
}



if ($argc < 7) {
	echo "usage:\n";
	echo "\tphp usage.php <ignore-root> \"localhost\" - - - <usage-file> [expand-file] [--force]\n";
	echo "\tphp usage.php <ignore-root> <hostname> <port> <user> <key> <usage-file> [expand-file] [--force]\n";
	die();
}

$ignore_root = intval($argv[1]);
$hostname = $argv[2];
$usage_file = $argv[6];
$expand_directories = ( empty($argv[7]) ? array() : read_json_array($argv[7]) );
$force = ( isset($argv[8]) && $argv[8] === "--force" );

if ($hostname != "localhost") {
	Executor::$host = $hostname;
	Executor::$port = ( is_numeric($argv[3]) ? intval($argv[3]) : 22 );
	Executor::$user = $argv[4];
	Executor::$key = $argv[5];
}

$space = new Capacity();

$mounts1 = list_mounts("type ext");
$mounts2 = list_mounts("type fuse.mfs");
$mounts = array_merge($mounts1, $mounts2);
asort($mounts);

if (!$force) {
	$json = read_json_array($usage_file);
	$usage = $json["usage"];
	$capacity = $json["capacity"];
} else {
	$usage = array();
	$capacity = array();
}

foreach ($mounts as $partition => $path) {
	if (in_array($path, $less_often_reports, true) && !$force)
		continue;

	if (in_array($path, $ignore_mounts, true) || strpos($path, "/srv/chunks/") !== false)
		continue;

	if ($path == "/" && $ignore_root)
		continue;

	$print = preg_replace("#/srv/(.*)/#s", "", $path);

	$usage[$print] = get_directory_usage($path, $expand_directories);
	$capacity[$print] = $space->get($partition, $path, "capacity");
}

if ($force) {
	$script = "/opt/heartbeat/scripts/checks/not-standby.sh";
	$devices = Executor::execute("ls $script >/dev/null 2>/dev/null && $script");

	foreach ($devices as $device)
		Executor::execute("hdparm -y $device 2>/dev/null");
}

echo prepare_json_array(array (
	"generated" => time(),
	"usage" => $usage,
	"capacity" => $capacity,
) );
