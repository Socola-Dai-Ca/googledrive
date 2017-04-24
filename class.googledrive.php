<?php
require_once 'vendor/autoload.php';
class GoogleDriveManager {
	/**
	 * @var string
	 */
	private $appName = 'Test';
	/**
	 * @var mixed
	 */
	private $service;
	/**
	 * @var mixed
	 */
	private $client;
	function __construct() {
		$client = new Google_Client();
		$client->setAuthConfig('client_secret.json');
		$client->setAccessType("offline");
		$client->setApprovalPrompt('force');
		$client->setScopes(array('https://www.googleapis.com/auth/drive'));
		$client->setAccessToken($_SESSION['access_token']);
		$this->client = $client;
		$this->service = new Google_Service_Drive($client);
	}

	/**
	 * @return google service
	 */
	public function getServices() {
		return $this->service;
	}

	/**
	 * @param $folderName
	 * @param $parentId
	 * @return object folder created
	 */
	public function createFolder($folderName, $parentId = null, $public = true) {
		try {
			if ($parentId == null) {
				$parentId = $this->rootId;
			}
			$fileMetadata = new Google_Service_Drive_DriveFile(array(
				'name' => $folderName,
				'mimeType' => 'application/vnd.google-apps.folder',
				'parents' => array(
					$parentId,
				),
			));
			$folder = $this->service->files->create($fileMetadata, array(
				'fields' => 'id',
			));
			if ($public) {
				$this->setShareForFile($folder->id);
			}
		} catch (Google_Service_Exception $e) {
			print "Error code :" . $e->getCode() . "\n";
			// Error message is formatted as "Error calling <REQUEST METHOD> <REQUEST URL>: (<CODE>) <MESSAGE OR REASON>".
			print "Error message: " . $e->getMessage() . "\n";
		} catch (Google_Exception $e) {
			// Other error.
			print "An error occurred: (" . $e->getCode() . ") " . $e->getMessage() . "\n";
		}
		return $folder;
	}

	/**
	 * @param $src
	 * @param $folderId
	 * @return object file uploaded
	 */
	public function uploadFile($src = '', $folderId = 'root') {
		try {
			$fileName = basename($src);
			$type = mime_content_type($src);
			$fileMetadata = new Google_Service_Drive_DriveFile(array(
				'name' => $fileName,
				'parents' => array(
					$folderId,
				),
			));
			echo $folderId;
			$content = file_get_contents($src);
			$file = $this->service->files->create($fileMetadata, array(
				'data' => $content,
				'mimeType' => $type,
				'uploadType' => 'multipart',
				'fields' => 'id',
			));
		} catch (Google_Service_Exception $e) {
			print "Error code :" . $e->getCode() . "\n";
			// Error message is formatted as "Error calling <REQUEST METHOD> <REQUEST URL>: (<CODE>) <MESSAGE OR REASON>".
			print "Error message: " . $e->getMessage() . "\n";
		} catch (Google_Exception $e) {
			// Other error.
			print "An error occurred: (" . $e->getCode() . ") " . $e->getMessage() . "\n";
		}

		return $file;
	}

	/**
	 * @param $fileId
	 */
	public function deleteFile($fileId) {
		try {
			$this->service->files->delete($fileId);
		} catch (Google_Service_Exception $e) {
			print "Error code :" . $e->getCode() . "\n";
			// Error message is formatted as "Error calling <REQUEST METHOD> <REQUEST URL>: (<CODE>) <MESSAGE OR REASON>".
			print "Error message: " . $e->getMessage() . "\n";
		} catch (Google_Exception $e) {
			// Other error.
			print "An error occurred: (" . $e->getCode() . ") " . $e->getMessage() . "\n";
		}
	}

	/**
	 * @param $rootId => parent id
	 * @param $onlyFolder => get only folder or files
	 * @return array files or folder
	 */
	public function getAllFiles($rootId, $onlyFolder = true, $order = 'name', $fields = 'id, name, webContentLink, mimeType, thumbnailLink, webViewLink') {
		try {
			$params = array(
				"'" . $rootId . "' in parents",
				'trashed=false',
			);
			if ($onlyFolder) {
				array_unshift($params, "mimeType='application/vnd.google-apps.folder'");
			}
			$parameters['q'] = implode(' and ', $params);
			$parameters['fields'] = "files(" . $fields . ")";
			$parameters['orderBy'] = $order;
			$files = $this->service->files->listFiles($parameters);
		} catch (Google_Service_Exception $e) {
			print "Error code :" . $e->getCode() . "\n";
			// Error message is formatted as "Error calling <REQUEST METHOD> <REQUEST URL>: (<CODE>) <MESSAGE OR REASON>".
			print "Error message: " . $e->getMessage() . "\n";
		} catch (Google_Exception $e) {
			// Other error.
			print "An error occurred: (" . $e->getCode() . ") " . $e->getMessage() . "\n";
		}
		return $files;
	}

	/**
	 * @param file id or folder id
	 */
	public function setShareForFile($fileId) {
		try {
			$this->service->getClient()->setUseBatch(true);
			$batch = $this->service->createBatch();
			$domainPermission = new Google_Service_Drive_Permission(array(
				'type' => 'anyone',
				'role' => 'reader',

				// 'domain' => $_SERVER['HTTP_HOST'],
			));
			$request = $this->service->permissions->create(
				$fileId,
				$domainPermission,
				array('fields' => 'id')
			);
			$batch->add($request, 'anyone');
			$results = $batch->execute();
			$this->service->getClient()->setUseBatch(false);
		} catch (Google_Service_Exception $e) {
			print "Error code :" . $e->getCode() . "\n";
			// Error message is formatted as "Error calling <REQUEST METHOD> <REQUEST URL>: (<CODE>) <MESSAGE OR REASON>".
			print "Error message: " . $e->getMessage() . "\n";
		} catch (Google_Exception $e) {
			// Other error.
			print "An error occurred: (" . $e->getCode() . ") " . $e->getMessage() . "\n";
		}
	}

	/**
	 * @param $name
	 * @return mixed
	 */
	public function fileExist($fileName, $folderId, $fields = 'id') {
		$pageToken = null;
		$result = false;
		do {
			$response = $this->service->files->listFiles(array(
				'q' => "trashed=false and name='" . $fileName . "' and '" . $folderId . "' in parents",
				'fields' => 'files(' . $fields . ')',
			));
			if (count($response->files) > 0) {
				return $response->files;
			}
		} while ($pageToken != null);
		return $result;
	}

	public function getFile($fileId, $fields = array('fields' => 'thumbnailLink, webContentLink, name, id, webViewLink')) {
		$file = $this->service->files->get($fileId, $fields);
		return $file;
	}

	public function moveFolder($oldFolderId, $newFolderId) {
		$files = $this->getAllFiles($oldFolderId, false);
		$listId = array();
		foreach ($files->files as $key => $file) {
			$old = $this->service->files->get($file->id, array('fields' => 'parents'));
			$previousParents = join(',', $old->parents);
			$emptyFileMetadata = new Google_Service_Drive_DriveFile();
			$this->service->files->update($file->id, $emptyFileMetadata, array(
				'addParents' => $newFolderId,
				'removeParents' => $previousParents,
				'fields' => 'id, parents, webContentLink'));
			$listId[] = $file->id;
		}
		return $listId;
	}
	public function rename($fileId, $name) {
		$file = new Google_Service_Drive_DriveFile();
		$file->setName($name);

		$updatedFile = $this->service->files->update($fileId, $file, array(
			'fields' => 'name',
		));
	}

	function runGooleAppScript($functionName, $params, $scriptId) {
		$service = new Google_Service_Script($this->client);
		$request = new Google_Service_Script_ExecutionRequest();
		$request->setFunction($functionName);
		$request->setParameters($params);
		try {
			$response = $service->scripts->run($scriptId, $request);
			if ($response->getError()) {
				$error = $response->getError()['details'][0];
				printf("Script error message: %s\n", $error['errorMessage']);
				if (array_key_exists('scriptStackTraceElements', $error)) {
					// There may not be a stacktrace if the script didn't start executing.
					print "Script error stacktrace:\n";
					foreach ($error['scriptStackTraceElements'] as $trace) {
						printf("\t%s: %d\n", $trace['function'], $trace['lineNumber']);
					}
				}
			} else {
				$resp = $response->getResponse();
				return $resp['result'];
			}
		} catch (Exception $e) {
			echo 'Caught exception: ', $e->getMessage(), "\n";
		}
		return null;
	}

	function createZip($folderId) {
		return $this->runGooleAppScript('zipFolderById', array($folderId));
	}
	function removeZip($folderId) {
		return $this->runGooleAppScript('deleteZip', array($folderId));
	}
}