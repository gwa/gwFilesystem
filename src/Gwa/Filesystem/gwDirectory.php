<?php
namespace Gwa\Filesystem;

use Gwa\Exception\gwFilesystemException;

/**
 * @brief Provides read and delete methods for directories in the server filesystem
 */
class gwDirectory
{
    /**
     * @access private
     */
    private $_dirpath;

    /**
     * @brief Constructor to be used only for existing directories.
     * @param string $filepath
     */
    public function __construct( $dirpath )
    {
        if (!is_dir($dirpath)) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_EXIST);
        }
        $this->_dirpath = realpath($dirpath).'/';
    }

    /**
     * @brief Creates a directory.
     * @param string $dirpath
     * @param string $dirname
     * @return gwDirectory
     * @deprecated  not intuitive.
     */
    public static function makeDirectory( $dirpath, $dirname='', $mode=0770, $replaceexisting=false )
    {
        $dirpath = realpath($dirpath).'/';
        $newdir = $dirpath.$dirname;
        if (!is_dir($dirpath)) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_EXIST);
        }
        if (file_exists($newdir)) {
            if (!$replaceexisting) {
                throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_ALREADY_EXIST);
            } else {
                $d = new gwDirectory($newdir);
                $d->delete();
            }
        }
        if (!mkdir($newdir, $mode, true)) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_WRITEABLE);
        }
        return new gwDirectory($newdir);
    }

    /**
     * @brief Creates a directoy recursively.
     * @param string $dir
     * @param string $mode
     * @return gwDirectory
     */
    public static function makeDirectoryRecursive( $dir, $mode=0770 )
    {
        if (is_dir($dir)) {
            return new gwDirectory($dir);
        }

        // may throw exception if not writable
        if (!@mkdir($dir, $mode, true)) {
            $e = new gwFilesystemException(
                gwFilesystemException::ERR_DIRECTORY_NOT_WRITEABLE,
                $dir
            );
            throw $e;
        }
        return new gwDirectory($dir);
    }

    /**
     * @brief Empties directory and any subdirectories.
     */
    public function emptyDirectory()
    {
        $files = $this->getFiles();
        foreach ($files as $file) {
            if (!unlink($this->_dirpath.$file)) {
                throw new gwFilesystemException(gwFilesystemException::ERR_DELETE);
            }
        }
        $directories = $this->getDirectories();
        foreach ($directories as $dir) {
            if (substr($dir, 0, 1) == '.') {
                continue;
            }
            $d = new gwDirectory($this->_dirpath.$dir);
            $d->emptyDirectory();
            $d->delete();
            unset($d);
        }
    }

    /**
     * @param string $dirname
     * @param int $mode
     * @param bool $ifnotexists
     * @return gwDirectory
     */
    public function makeSubDirectory( $dirname, $mode=0770, $ifnotexists=true )
    {
        $path = $this->_dirpath.$dirname;
        try {
            return self::makeDirectory($this->_dirpath, $dirname, $mode, false);
        } catch (\Exception $e) {
            if ($ifnotexists && $e->getMessage() == gwFilesystemException::ERR_DIRECTORY_ALREADY_EXIST) {
                return new gwDirectory($path);
            }
            throw($e);
        }
    }

    /**
     * @brief Deletes this directory.
     * @param bool $recursive delete any directories and fles contained in this directory
     */
    public function delete( $recursive=true )
    {
        if (!is_dir($this->_dirpath)) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_EXIST);
        }
        if ($recursive) {
            $this->emptyDirectory();
        }
        if (!rmdir($this->_dirpath)) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DELETE);
        }
    }

    /**
     * @brief Get paths of all directories within this directory.
     *
     * @return array
     * @throws gwFileSystemException
     */
    public function getDirectories()
    {
        $directories = array();

        if (!$dh = opendir($this->_dirpath)) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_READABLE);
        }

        while (($file = readdir($dh)) !== false) {
            if (is_dir($this->_dirpath.$file) && $file!='.' && $file!='..' && substr($file, 0, 1)!='.') {
                $directories[] = $file;
            }
        }
        sort($directories);
        return $directories;
    }

    /**
     * @brief Get paths of all files in this directory
     * @param string $filter returns only those files whose filename contains this string
     * @return array
     * @throws gwFileSystemException
     */
    public function getFiles( $filter='' )
    {
        $files = array();

        if (!$dh = opendir($this->_dirpath)) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_READABLE);
        }

        while (($file = readdir($dh)) !== false) {
            if (!is_file($this->_dirpath. $file)) {
                continue;
            }
            if ($filter) {
                if (stristr($file, $filter)) {
                    $files[] = $file;
                }
            } else {
                $files[] = $file;
            }
        }
        sort($files);
        return $files;
    }

    /**
     * @brief Copy files from one directory to another
     *
     * @param string $filter substring
     * @param string $targetdirectory path to target directory
     * @param bool $delete delete files after copying
     */
    public function copyFiles( $filter='', $targetdirectory='', $delete=false )
    {
        if ($targetdirectory instanceof gwDirectory) {
            $targetdirectory = $targetdirectory->getPath();
        }

        $files = $this->getFiles($filter);

        if (!is_writeable($targetdirectory)) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_WRITEABLE);
        }

        for ($i=0, $l=count($files); $i<$l; $i++) {
            copy($this->_dirpath.$files[$i], $targetdirectory.$files[$i]);
            if ($delete) {
                if (!unlink($this->_dirpath. $files[$i])) {
                    throw new gwFilesystemException(gwFilesystemException::ERR_DELETE);
                }
            }
        }
    }

    /**
     * @brief Rename files sequentially.
     * @param int $pad no. of characters to pad to with zeros, e.g. $pad=4 results in '0045'
     * @param string $prefix
     * @param string $filter only files containing substring
     * @param int $start number to start from
     */
    public function renameSequential( $pad=0, $prefix='', $filter='', $start=0 )
    {
        $files = $this->getFiles($filter);
        $count = $start;
        for ($i=0,$l=count($files); $i<$l; $i++) {
            $file = $files[$i];
            $ending = strtolower(substr($file, strpos($file, '.')));
            $base = substr($file, 0, strpos($file, '.'));
            $newname = $this->getPath(). '/' . $prefix . str_pad($count, $pad, '0', STR_PAD_LEFT) . $ending;
            if (!rename($this->getPath().'/'.$file, $newname)) {
                throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_WRITEABLE);
            }
            $count++;
        }
    }

    /**
     * @brief gets the path, with trailing slash
     * @return string
     */
    public function getPath()
    {
        return $this->_dirpath;
    }

    /**
     * @brief is directory writable?
     * @return bool
     */
    public function isWritable()
    {
        return is_writable($this->getPath());
    }

    /**
     * @brief is directory readable?
     * @return bool
     */
    public function isReadable()
    {
        return is_readable($this->getPath());
    }
}
