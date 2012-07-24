<?php
/**
* Image compressor, uses PunyPNG.com API <http://www.punypng.com>
* Supports PNG, JPEG and GIF images
* Works on local files and urls
* Can be used in single file and batch modes
* More info on PunyPNG API <http://www.punypng.com/api>
*
* @author Mark Rolich <mark.rolich@gmail.com>
*/
class PunyPNG
{
    /**
    * @var string - PunyPNG API URL
    */
    const URL = 'http://www.punypng.com/api/optimize';

    /**
    * @var string - PunyPNG image file download URL
    */
    const DOWNLOAD_IMG_URL = 'http://punypng.com/processor/download_image/';

    /**
    * @var string - PunyPNG zip file download URL
    */
    const DOWNLOAD_ZIP_URL = 'http://punypng.com/api/download_zip';

    /**
    * @var string - page not found error message
    */
    const PAGE_NOT_FOUND = 'Optimizer page not found';

    /**
    * @var string - local file not found error message
    */
    const FILE_NOT_FOUND = 'File %s not found';

    /**
    * @var string - daily quota error message
    */
    const DAILY_QUOTA_ERROR = 'Daily quota of 50 images is exceeded';

    /**
    * @var string - local file too large error message
    */
    const FILE_TOO_LARGE = 'File %s exceeds 500 Kb limit';

    /**
    * @var resource - cURL handle
    */
    private $ch;

    /**
    * @var bool - set batch mode on/off
    */
    public $batchMode = false;

    /**
    * @var string - group_id used in batch mode (string length 40 bytes)
    */
    private $groupId;

    /**
    * @var string - PunyPNG API key
    */
    public $apiKey;

    /**
    * @var string - path to save compressed images
    */
    public $savePath = '.';

    /**
    * @var mixed - array of compressed images information
    */
    public $info;

    /**
    * @var mixed - array of errors occured during compression
    */
    public $errors;

    /**
    * Constructor
    *
    * Creates cURL session
    */
    public function __construct()
    {
        $this->ch = curl_init(self::URL);
    }

    /**
    * Destructor
    *
    * Closes cURL session
    */
    public function __destruct()
    {
        curl_close($this->ch);
    }

    /**
    * Prepares files paths list for upload
    *
    * Resolves relative paths to absolute paths,
    * checks if file is exist and filesize is under 500Kb,
    * adds '@' symbol before local file path to use in cURL upload process
    *
    * @param $files mixed - array of local files paths and urls
    * @return mixed - array of prepared file paths
    */
    public function prepare($files)
    {
        $prepared = array();

        foreach ($files as $url) {
            $urlParts = parse_url($url);

            $name = pathinfo($urlParts['path'], PATHINFO_BASENAME);

            if (!isset($urlParts['scheme'])) {
                $path = realpath($urlParts['path']);

                if ($path === false) {
                    $this->errors[] = sprintf(self::FILE_NOT_FOUND, $name);
                    $path = null;
                } elseif (round(filesize($path)/1024) > 500) {
                    $this->errors[] = sprintf(self::FILE_TOO_LARGE, $name);
                    $path = null;
                } else {
                    $path = '@' . $path;
                }
            } else {
                $path = $url;
            }

            if ($path != null) {
                $prepared[] = array(
                    'path' => $path,
                    'name' => $name
                );
            }
        }

        return $prepared;
    }

    /**
    * Uploads file to PunyPNG server
    *
    * Uploads local files using POST and URLs using GET methods,
    * creates groupId (40 byte length random unique string) if batch mode is on,
    * retrieves JSON encoded response and http status code from PunyPNG API server
    *
    * @param $fileInfo mixed - array of file path and name
    * @return mixed - array of JSON decoded response from server (object) and http status code (int)
    */
    public function upload($fileInfo)
    {
        extract($fileInfo);

        $postFields = array('key' => $this->apiKey);
        $postFields['img'] = $path;

        if ($this->batchMode) {
            if ($this->groupId == null) {
                $this->groupId = uniqid(str_repeat(mt_rand(0,9), 27));
            }

            $postFields['group_id'] = $this->groupId;
        }

        if (strpos($path, '@') !== false) {
            curl_setopt($this->ch, CURLOPT_URL, self::URL);
            curl_setopt($this->ch, CURLOPT_POST, 1);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postFields);
        } else {
            curl_setopt($this->ch, CURLOPT_URL, self::URL . '?' . http_build_query($postFields));
            curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
        }

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);

        $result = json_decode(curl_exec($this->ch));

        $status = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        return array($result, $status);
    }

    /**
    * Downloads file from PunyPNG server
    *
    * @param $url string - PunyPNG image/zip file download url
    * @param $filename string - file path to save downloaded file
    */
    public function download($url, $filename)
    {
        $file = fopen($filename, 'w');

        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_FILE, $file);

        curl_exec($this->ch);
    }

    /**
    * Uploads files to PunyPNG server, parses response,
    * downloads compressed files
    *
    * @param $files mixed - array of local filepaths and URLs
    */
    public function compress($files)
    {
        $this->ch = curl_init(self::URL);

        $files = $this->prepare($files);

        $compressed = array();

        foreach ($files as $file) {
            extract($file);
            list($result, $status) = $this->upload($file);

            switch ($status) {
                case 200:
                    if (isset($result->error)) {
                        if (strpos($result->error, 'API key') !== false) {
                            $this->errors[] = $result->error;
                            break 2;
                        } else {
                            $this->errors[] = 'Failed on file ' . $name . ': ' . $result->error;
                        }
                    } else {
                        $pathChunks = explode('/', $result->optimized_url);
                        $hash = $pathChunks[count($pathChunks) - 2];
                        $compressed[$name] = $hash;
                        $this->info[] = $result;
                    }

                    break 1;
                case 404:
                    $this->errors[] = self::PAGE_NOT_FOUND;
                    break 2;
                case 413:
                    $this->errors[] = sprintf(self::FILE_TOO_LARGE, $name);
                    break 1;
                case 500:
                    $this->errors[] = self::DAILY_QUOTA_ERROR;
                    break 2;
            }
        }

        if (!empty($compressed)) {
            if ($this->batchMode) {
                $downloadURL = self::DOWNLOAD_ZIP_URL . '?group_id=' . $this->groupId;
                $this->download($downloadURL, $this->savePath . '/images.zip');
            } else {
                foreach ($compressed as $filename => $hash) {
                    if (strpos($filename, '.gif') !== false) {
                        $filename = str_replace('.gif', '.png', $filename);
                    }

                    $downloadURL = self::DOWNLOAD_IMG_URL . $hash . '?filename=' . $filename;
                    $this->download($downloadURL, $this->savePath . '/' . $filename);
                }
            }
        }
    }

    /**
    * Generate HTML presentation of compressed files details
    *
    * @return string - HTML code of compressed files details
    */
    public function printInfo()
    {
        $result = '';

        if (!empty($this->info)) {
            $result = '<table class="info" border="1">';
            $result .= '<thead>
                        <tr>
                        <th>Image ID</th>
                        <th>Image name</th>
                        <th>Original size, bytes</th>
                        <th>Optimized size, bytes</th>
                        <th>Savings, bytes</th>
                        <th>Savings, percent</th>
                        </tr>
                        </thead>';

            $i = 1;

            foreach ($this->info as $imgInfo) {
                $result .= '<tr>';
                $result .= '<td>' . $i . '</td>';
                $result .= '<td>' . substr($imgInfo->optimized_url, strrpos($imgInfo->optimized_url, '/') + 1) . '</td>';
                $result .= '<td>' . $imgInfo->original_size . '</td>';
                $result .= '<td>' . $imgInfo->optimized_size . '</td>';
                $result .= '<td>' . $imgInfo->savings_bytes . '</td>';
                $result .= '<td>' . $imgInfo->savings_percent . '</td>';
                $result .= '</tr>';
                $i++;
            }

            $result .= '</table>';
        }

        return $result;
    }

    /**
    * Generate HTML presentation of errors occured
    *
    * @return string - HTML presentation of errors occured
    */
    public function printErrors()
    {
        $result = '';

        if (!empty($this->errors)) {
            $result = '<ul class="errors">';

            foreach ($this->errors as $error) {
                $result .= '<li>' . $error . '</li>';
            }

            $result .= '</ul>';
        }

        return $result;
    }
}
?>