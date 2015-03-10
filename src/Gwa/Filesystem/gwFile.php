<?php
namespace Gwa\Filesystem;

use Gwa\Exception\gwFilesystemException;

/**
 * @brief Provides read, write, append and delete methods for text files in the server filesystem
 *
 * @ingroup data
 * @author Timothy Groves
 */
class gwFile
{
    /**
     * @access private
     *
     * @var string
     */
    private $fullpath;

    /**
     * constructor
     *
     * @param string $fullpath
     */
    public function __construct($fullpath)
    {
        $this->fullpath = $fullpath;
    }

    /**
     * Checks whether file exists and if it is a regular file
     * (i.e. not a directory)
     *
     * @return bool
     */
    public function exists()
    {
        return file_exists($this->fullpath) && is_file($this->fullpath) ? true : false;
    }

    /**
     * Returns true if the file is writeable, or if the file does not exist but
     * the directory is writable, i.e. file can be written
     *
     * @return bool
     */
    public function isWritable()
    {
        if (!$this->exists()) {
            return is_writable($this->getPath(PATHINFO_DIRNAME));
        }

        return is_writable($this->fullpath);
    }

    /**
     * @return bool
     */
    public function isReadable()
    {
        return is_readable($this->fullpath);
    }

    /**
     * @param octal $permissions
     *
     * @return bool
     */
    public function setPermissions($permissions)
    {
        return chmod($this->fullpath, $permissions);
    }

    /**
     * Returns the directory containing this file
     *
     * @return gwDirectory
     */
    public function getDirectory()
    {
        return new gwDirectory($this->getPath(PATHINFO_DIRNAME));
    }

    /**
     * returns content of file as a string
     *
     * @return string
     *
     * @throws gwFilesystemException
     */
    public function getContent()
    {
        if (!$this->exists()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_EXIST, $this->getPath());
        }

        if (!$this->isReadable()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_READABLE, $this->getPath());
        }

        return file_get_contents($this->fullpath);
    }

    /**
     * replace content of file with string
     *
     * @param string $content
     *
     * @return int bytes written, or false on failure
     *
     * @throws gwFilesystemException
     */
    public function replaceContent($content)
    {
        if (!$this->isWritable()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_WRITEABLE, $this->getPath());
        }

        $handle = $this->_getHandle('w');
        $byteswritten = fwrite($handle, $content);
        fclose($handle);

        return $byteswritten;
    }

    /**
     * appends content to this file
     *
     * @param string $content
     *
     * @return int bytes written
     *
     * @throws gwFilesystemException
     */
    public function appendContent($content)
    {
        if (!$this->isWritable()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_WRITEABLE, $this->getPath());
        }

        $handle = $this->_getHandle('a');
        $byteswritten = fwrite($handle, $content);
        fclose($handle);

        return $byteswritten;
    }

    /**
     * @deprecated
     */
    public function parseIni()
    {
        if (!$this->exists()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_EXIST);
        }

        if (!$this->isReadable()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_READABLE);
        }

        return parse_ini_file($this->fullpath);
    }

    /**
     * return a file pointer handle for this file
     *
     * @param string $mode
     *
     * @return resource
     *
     * @throws gwFilesystemException
     */
    private function _getHandle($mode = 'r')
    {
        if (!file_exists(dirname($this->fullpath))) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_EXIST);
        }

        if (!$handle = @fopen($this->fullpath, $mode)) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_EXIST);
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_READABLE, $this->getPath());
        }

        return $handle;
    }

    /**
     * deletes the file
     *
     * @return boolean
     *
     * @throws gwFilesystemException
     */
    public function delete()
    {
        if (!$this->exists()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_EXIST, $this->getPath());
        }

        if (!$this->isWritable()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_WRITEABLE, $this->getPath());
        }

        return unlink($this->fullpath);
    }

    /**
     * Moves file to directory. Creates directory if not exist.
     *
     * @param gwDirectory|string $dir
     *
     * @throws gwFilesystemException
     *
     * @return boolean
     */
    public function moveTo($dir)
    {
        if (!is_a($dir, 'Gwa\Filesystem\gwDirectory')) {
            $dir = gwDirectory::makeDirectoryRecursive($dir);
        }

        if (!$dir->isWritable()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_DIRECTORY_NOT_WRITEABLE, $dir->getPath());
        }

        $newpath = $dir->getPath().$this->getPath(PATHINFO_BASENAME);

        if (rename($this->getPath(), $newpath)) {
            $this->fullpath = $newpath;

            return true;
        }

        return false;
    }

    /**
     * outputs the file with a headers, and exits the script
     *
     * @param string $filename
     *
     * @deprecated
     */
    public function download($filename = null)
    {
        $headers = $this->getHeaders($filename);
        $headers->send();
        readfile($this->fullpath);
        exit;
    }

    /**
     * @return array
     */
    public function getDownloadHeaders($filename = null)
    {
        if (!$this->isReadable()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_READABLE, $this->getPath());
        }

        if (!$filename) {
            $filename = basename($this->fullpath);
        }

        $headers = array();
        $headers['Content-type'] = $this->getMimeType(true);
        $headers['Content-disposition'] = 'attachment; filename='.$filename;
        $headers['Content-Transfer-Encoding'] =  'binary';
        $headers['Expires'] = '0';
        $headers['Cache-Control'] = 'must-revalidate, post-check=0, pre-check=0';
        $headers['Pragma'] = 'public';
        $headers['Content-Length'] = filesize($this->fullpath);

        return $headers;
    }

    /**
     * @param  boolean $withencoding
     * @return string
     */
    public function getMimeType($withencoding = false)
    {
        if (!$this->exists()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_EXIST, $this->getPath());
        }

        $filename = realpath($this->fullpath);

        if (function_exists('finfo_open')) {
            $finfo = finfo_open($withencoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);

            return $mimetype;
        }

        // FALLBACK:

        $mimetypes = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

        // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

        // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

        // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

        // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

        // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

        // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $explode = explode('.', $filename);

        if ($ext = end($explode)) {
            $ext = strtolower($ext);
        }

        if ($this->isImage()) {
            $data = getimagesize($this->fullpath);

            return $data['mime'];
        } elseif ($ext && array_key_exists($ext, $mimetypes)) {
            return $mimetypes[$ext];
        }

        return;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        if (!$this->exists()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_EXIST, $this->getPath());
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_ENCODING);
            $encoding = finfo_file($finfo, realpath($this->fullpath));
            finfo_close($finfo);

            return $encoding;
        }

        return;
    }

    /**
     * @param  string $pathinfo PATHINFO_DIRNAME, PATHINFO_BASENAME, PATHINFO_EXTENSION or PATHINFO_FILENAME
     * @return string
     */
    public function getPath($pathinfo = null)
    {
        if (!$pathinfo) {
            return $this->fullpath;
        }

        return pathinfo($this->fullpath, $pathinfo);
    }

    /**
     * @return string
     */
    public function getDirPath()
    {
        return $this->getPath(PATHINFO_DIRNAME);
    }

    /**
     * @return string
     */
    public function getBasename()
    {
        return $this->getPath(PATHINFO_BASENAME);
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->getPath(PATHINFO_EXTENSION);
    }

    /**
     * is file an image?
     * @return bool
     */
    public function isImage()
    {
        if (!$this->exists()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_EXIST, $this->getPath());
        }

        if (!$this->isReadable()) {
            throw new gwFilesystemException(gwFilesystemException::ERR_FILE_NOT_READABLE, $this->getPath());
        }

        $data = @getimagesize($this->fullpath);
        if (!$data || !$data[0] || !$data[1]) {
            return false;
        }

        return true;
    }

    /**
     * @return int unix timestamp
     */
    public function getModificationTime()
    {
        return filemtime($this->fullpath);
    }
}
