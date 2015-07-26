<?php

namespace Backup;

class Backup
{
    private $backupFolderParent = '.';
    private $backupFolderDate = '';
    private $backupFolder = '';
    private $db = array();
    private $backupFiles = array();
    private $backupStorage = null;

    public function __construct($backupFolder, $host, $user, $password)
    {
        $this->backupFolderParent = $backupFolder;
        $this->backupFolderDate = date('Y-m-d');

        $backupFolder .= '/' . $this->backupFolderDate;
        if (!is_dir($backupFolder)) {
            mkdir($backupFolder);
        }

        $this->backupFolder = realpath($backupFolder);

        $this->db['host'] = $host;
        $this->db['user'] = $user;
        $this->db['password'] = $password;
    }

    public function setBackupStorage($backupStorage)
    {
        $this->backupStorage = $backupStorage;
    }

    public function getDatabase()
    {
        $result = array();
        $exclude = array('information_schema', 'performance_schema', 'mysql');

        $link = mysqli_connect($this->db['host'], $this->db['user'], $this->db['password']);
        $query = mysqli_query($link, "SHOW DATABASES;");
        while( $row = $query->fetch_row() ) {
            $database = $row['0'];
            if (!in_array($database, $exclude)) {
                $result[] = $database;
            }
        }

        return $result;
    }

    public function addBackupFile($vaultName, $file, $description)
    {
        if (!array_key_exists($vaultName, $this->backupFiles)) {
            $this->backupFiles[$vaultName] = array();
        }
        $this->backupFiles[$vaultName][] = array(
            'file' => $file,
            'description' => $description
        );
    }

    public function backupFolders($vaultName, $folders = array())
    {
        foreach ($folders as $folder) {
            $folderId = $this->getNormalizedName($folder);
            $destinationFile = "{$this->backupFolder}/{$this->backupFolderDate}_{$folderId}";
            //$destinationFile = "{$this->backupFolder}/{$this->backupFolderDate}-{$folderId}";
            $compressedFolder = $this->compress($folder, $destinationFile);

            $this->addBackupFile($vaultName, $compressedFolder, $folderId);
        }
    }

    public function getNormalizedName($name)
    {
        $result = $name;
        $result = str_replace('\\', "-", $result);
        $result = str_replace('/', "-", $result);
        $result = str_replace(':', "-", $result);
        $result = trim($result, "-");
        return $result;
    }

    public function compress($src, $destination)
    {
        $cmd = "pack {$src} {$destination}";
        system($cmd);
        return $destination.".tar.gz";
    }

    public function backupDb($vaultName)
    {
        $databases = $this->getDatabase();

        $mysqldump = "mysqldump";

        foreach ($databases as $database) {

            $fileName = "{$database}-db";
            //$fileName = "{$this->backupFolderDate}-{$database}-db";
            $databaseFile = "{$this->backupFolder}/{$this->backupFolderDate}_{$fileName}.sql";

            $cmd = "{$mysqldump} -u{$this->db['user']} -p{$this->db['password']} -h{$this->db['host']} " .
                   "-Q -c -C --add-drop-table --add-locks --quick --lock-tables " .
                   "{$database} > {$databaseFile}";
            system($cmd);

            $compressedFile = $this->compress($databaseFile, $databaseFile);
            unlink($databaseFile);
            $this->addBackupFile($vaultName, $compressedFile, $this->getNormalizedName($fileName));
        }
    }

    public function backup($folders = array(), $gitFolders = array())
    {
        $this->backupDb('BackupDb');
        $this->backupFolders('BackupDirectory', $folders);
        $this->backupFolders('BackupGit', $gitFolders);

        $this->saveBackupFiles();
        $this->cleanup();

    }

    public function cleanup()
    {
        foreach ($this->backupFiles as $vaultName => $filesInfo) {
            foreach ($filesInfo as $fileInfo) {
                unlink($fileInfo['file']);
            }
        }
        rmdir($this->backupFolder);
    }

    public function saveBackupFiles()
    {
        if (!$this->getBackupStorage()) {
            throw new \Exception('Storage not defined');
        }

        foreach ($this->backupFiles as $vaultName => $filesInfo) {
            foreach ($filesInfo as $fileInfo) {
                echo "uploading {$fileInfo['file']} to {$vaultName}\n";
                $result = $this->getBackupStorage()->save($vaultName, $fileInfo['file'], $fileInfo['description']);
                if ($result === false) {
                    system("4push Backup 'Backup Failed {$fileInfo['file']}'", $retVal);
                }

                if (intval(date("d")) != 8) {
                    $deleteFileName = basename($fileInfo['file']);
                    $deleteFile = preg_replace("#^\d{4}-\d{2}-\d{2}#is", date("Y-m-d", strtotime("-7 day")), $deleteFileName);
                    $this->getBackupStorage()->delete($vaultName, $deleteFile);
                }
            }
        }
    }

    public function getBackupStorage()
    {
        return $this->backupStorage;
    }
}




