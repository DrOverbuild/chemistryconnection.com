<?php
/**
 * Created by PhpStorm.
 * User: jasper
 * Date: 2/24/19
 * Time: 9:09 PM
 */

require_once __DIR__ . '/DataSheet.php';
require_once __DIR__ . '/Permission.php';

class Connection {
	/** @var mysqli */
	var $conn;

	public function __construct() {
		$config = parse_ini_file(__DIR__ . '/private/db.ini');

		$servername = $config['servername'];
		$username = $config['username'];
		$password = $config['password'];
		$dbname = $config['dbname'];

		$conn = new mysqli($servername, $username, $password, $dbname);

		if ($conn->connect_error) {
			echo "Connect error: " . $conn->connect_error;
			$this->conn = null;
			$this->connect_error = $conn->connect_error;
		} else {
			$this->conn = $conn;
		}
	}

	function prepare(string $query) {
		if ($this->conn) {
			return $this->conn->prepare($query);
		} else {
			return false;
		}
	}

	function dataSheetFromRow($row) {
		return new DataSheet($row['name'], $row['filepath'], new DateTime($row['date_uploaded']), intval($row['id']), intval($row['type']));
	}

	function getDataSheetTypes() {
		$stmt = $this->conn->prepare("SELECT `id`, `display_name` FROM `filetype`");
		$stmt->execute();

		$result = $stmt->get_result();

		$dataSheetTypes = array();

		while ($row = $result->fetch_assoc()) {
			array_push($dataSheetTypes, ["id" => $row['id'], "display_name" => $row['display_name']]);
		}

		return $dataSheetTypes;
	}

	// FILE READ

	/**
	 * @return DataSheet[]
	 * Null if database connection fails, array of SafetyDataSheets if successful
	 */
	function getAllFiles($dataSheetType = -1, $pagenumber = 0, $pagesize = 25) {

		$offset = $pagenumber * $pagesize;

		$query = "SELECT * FROM `file` ORDER BY `id` ASC";

		if ($dataSheetType != -1) {
			$query = "SELECT * FROM `file` WHERE `type` = $dataSheetType ORDER BY `id` ASC";
		}

		$query .= " LIMIT $pagesize OFFSET $offset";

		$stmt = $this->conn->prepare($query);

		$stmt->execute();

		$resut = $stmt->get_result();

		$files = array();

		while ($row = $resut->fetch_assoc()) {
			array_push($files, $this->dataSheetFromRow($row));
		}

		return $files;
	}

	/**
	 * Returns the number of pages based on the number of records in the file table
	 * @return integer
	 */
	function getNumPagesAllFiles($pagesize = 25, $dataSheetType = -1) {
		$query = "SELECT COUNT(*) FROM `file`";

		if ($dataSheetType > -1) {
			$query.=" WHERE `type` = $dataSheetType";
		}

		$stmt = $this->conn->prepare($query);
		$stmt->execute();
		$result = $stmt->get_result();
		if ($result->num_rows == 1) {
			$filecount = intval($result->fetch_row()[0]);
			$pages = $filecount / $pagesize;

			return ceil($pages);
		}

		return 0;
	}

	/**
	 * Get five most recent files. Useful if you just uploaded a file and need to copy the link.
	 * @return DataSheet[]
	 * Null if database connection fails, array of SafetyDataSheets if successful.
	 */
	function getRecentFiles($dataSheetType = -1) {

		$query = "SELECT * FROM `file` ORDER BY date_uploaded DESC LIMIT 0,5";

		if ($dataSheetType != -1) {
			$query = "SELECT * FROM `file` WHERE `type` = $dataSheetType ORDER BY date_uploaded DESC LIMIT 0,5";
		}

		$stmt = $this->conn->prepare($query);
		$stmt->execute();

		$resut = $stmt->get_result();

		$files = array();

		while ($row = $resut->fetch_assoc()) {
			array_push($files, $this->dataSheetFromRow($row));
		}

		return $files;
	}

	function search(string $query, $dataSheetType = 1) {
		$mysqlquery = "SELECT * FROM `file` WHERE `name` LIKE ?";

		if ($dataSheetType != -1) {
			$mysqlquery = "SELECT * FROM `file` WHERE  `name` LIKE ? AND `type` = $dataSheetType";
		}


		$query = "%".$query."%";
		$stmt = $this->conn->prepare($mysqlquery);
		$stmt->bind_param('s', $query);
		$stmt->execute();

		$resut = $stmt->get_result();

		$files = array();

		while ($row = $resut->fetch_assoc()) {
			array_push($files, $this->dataSheetFromRow($row));
		}

		return $files;
	}

	// FILE MANAGEMENT
	function getFile($id) {
		$fileidint = intval($id);
		$stmt = $this->prepare("SELECT * FROM `file` WHERE `id` = ?");
		$stmt->bind_param('i', $fileidint);
		$stmt->execute();

		$result = $stmt->get_result();

		while ($row = $result->fetch_assoc()) {
			return $this->dataSheetFromRow($row);
		}

		return null;
	}

	/**
	 * Adds new file to database records
	 * @param $sds DataSheet
	 */
	function addNewFile($sds) {
		$stmt = $this->prepare("INSERT INTO `file` (type, name, filepath, date_uploaded) VALUES (?, ?, ?, NOW())");
		$stmt->bind_param("iss",$sds->fileType, $sds->name, $sds->filepath);
		$stmt->execute();
	}

	/**
	 * Deletes file from database records
	 * @param $sds DataSheet
	 */
	function deleteFile($sds) {
		$stmt = $this->prepare("DELETE FROM `file` WHERE id = ?");
		$stmt->bind_param("i", $sds->id);
		$stmt->execute();
	}

	/**
	 * Renames file in database records
	 * @param $sds DataSheet
	 */
	function renameFile($sds) {
		$stmt = $this->prepare("UPDATE `file` SET `name` = ?, `filepath` = ? WHERE id = ?");
		$stmt->bind_param("ssi", $sds->name, $sds->filepath, $sds->id);
		$stmt->execute();
	}

	// PERMISSIONS
	function listPermissions($public = false) {
		$query = "SELECT * FROM `perms` ORDER BY `perms`.`usergroup` ASC, `perms`.`title` ASC ";

		if ($public) {
			$query = "SELECT * FROM `perms`  WHERE `usergroup` = '3 General'";
		}

		$stmt = $this->prepare($query);
		$stmt->execute();

		$result = $stmt->get_result();

		$perms = array();

		while ($row = $result->fetch_assoc()) {
			$id = intval($row['id']);
			$title     = $row['title'];
			$usergroup = $row['usergroup'];
			$desc      = $row['description'];

			$perm = new Permission($id, $title, $usergroup, $desc);

			array_push($perms, $perm);
		}

		return $perms;
	}

	function listPublicPermissions() {
		return $this->listPermissions(true);
	}

	function listDefaultPermissions() {
		$query = "SELECT * FROM `userperms`, `perms` WHERE `userperms`.`permid`=`perms`.`id` AND `userperms`.`uid` = \"default\";";
		$stmt = $this->prepare($query);
		$stmt->execute();

		$result = $stmt->get_result();

		$perms = array();

		while ($row = $result->fetch_assoc()) {
			$id = intval($row['id']);
			$title     = $row['title'];
			$usergroup = $row['usergroup'];
			$desc      = $row['description'];

			$perm = new Permission($id, $title, $usergroup, $desc);

			array_push($perms, $perm);
		}

		return $perms;
	}
}