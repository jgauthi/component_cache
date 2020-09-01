<?php
/*******************************************************************************
 * @name: Cache
 * @note: Data storage in the form of temporary files (cache)
 * @author: Jgauthi <github.com/jgauthi>, created at [14mars2009]
 * @version 1.1

 *******************************************************************************/

namespace Jgauthi\Component\Cache;

use InvalidArgumentException;

class Cache
{
    public $cacheName;
    public $folder;
    public $date;
    protected $cacheFile;
    protected $content;
    protected $type;
    protected $cacheExists = null;

    /**
     * cache constructor.
     *
     * @param string $cacheName
     * @param string $date
     * @param string $folder
     * @param string $type
     */
    public function __construct($cacheName, $date, $folder = 'tmp', $type = 'tpl')
    {
        // Type de cache
        if (preg_match('#^dat#i', $type)) {
            $this->type = 'data';
        } elseif (preg_match('#(html|tpl)#i', $type)) {
            $this->type = 'template';
        } elseif (preg_match('#(sql|db)#i', $type)) {
            $this->type = 'sql';
        } else {
            $this->type = 'file';
        }

        if (!is_dir($folder) && !mkdir($folder, 0777)) {
            throw new InvalidArgumentException(
                "Unable to create folder '{$folder}', class" . __CLASS__ . ' cannot work.'
            );
        }

        $this->folder = $folder.'/';

        $this->cacheName = md5($cacheName.$_SERVER['HTTP_HOST']);
        $this->date = $this->calculTime($date);
        $this->cacheFile = $this->folder.'cache_'.$this->cacheName.$this->getExtension($this->type);
    }

    /**
     * @return bool|null
     */
    public function exists()
    {
        // Si le cache existe
        if (null === $this->cacheExists) {
            $this->cacheExists = false;

            if (is_readable($this->cacheFile)) {
                if (@filemtime($this->cacheFile) > (time() - $this->date)) {
                    $this->cacheExists = true;
                } else {
                    $this->delete();
                }
            }
        }

        return $this->cacheExists;
    }

    /**
     * Content manager
     *
     * @param string|null $content
     * @return mixed
     */
    public function content($content = null)
    {
        if (('data' === $this->type || 'sql' === $this->type) && (!is_array($content) && !is_object($content))) {
            $this->content = unserialize(file_get_contents($this->cacheFile));
        } elseif ('template' === $this->type && $this->cacheExists) {
            $this->content = file_get_contents($this->cacheFile);
        } elseif ('template' === $this->type && !empty($content)) {
            $this->content = $content;
        } elseif (!empty($content)) {
            $this->content = $content;
        }

        return $this->content;
    }

    /**
     * @param string $content
     * @return bool
     */
    public function create($content = '')
    {
        if (empty($content)) {
            if (empty($this->content)) {
                throw new InvalidArgumentException('Could not create cache, no content');
            }
            $content = $this->content;
        }

        // Signature
        switch ($this->type) {
            case 'template':
                $content .= "\n\n".'<!-- CACHE {'.$this->cacheName.'} time: '.date('d-m-Y, H:i:s').' -->'."\n\n";
                break;

            case 'data': case 'sql':
                $content = serialize($content);
                break;

            default:
                throw new InvalidArgumentException('Donné du Cache ['.$this->type.'] id: {'.$this->cacheName.'} corrompu');
        }

        // Cache creation
        $file = @fopen($this->cacheFile, 'w');
        fwrite($file, $content);
        fclose($file);

        return file_exists($this->cacheFile);
    }

    /**
     * @param string|null   $filename
     * @param string $type
     *
     * @return bool
     */
    public function delete($filename = null, $type = 'tpl')
    {
        if (is_null($filename)) {
            $file = $this->cacheFile;
        } else {
            $file = $this->folder.md5($filename).$this->getExtension($type);
        }

        return @unlink($file);
    }

    /**
     * Clean cache (delete all caches created by this class).
     *
     * @param string|null $dossier
     *
     * @return bool|int
     */
    public function clearCache($dossier = null)
    {
        if (null === $dossier) {
            $dossier = $this->folder;
        }

        // Vérifier le dossier
        if (is_dir($dossier)) {
            $dir = opendir($dossier);
            $nb_file_delete = 0;
            while ($file = readdir($dir)) {
                if ('.' === $file && '..' === $file) {
                    continue;
                }

                $ext = $this->getExtension($this->type);

                // Effacer les fichiers caches
                if (preg_match("#^cache_([a-f0-9]{32})\.".$ext.'$#i', $file) && @unlink($file)) {
                    ++$nb_file_delete;
                }
            }
            closedir($dir);

            return $nb_file_delete;
        }

        return false;
    }

    /**
     * @param string $date
     * @return int
     */
    protected function calculTime($date)
    {
        if (preg_match('#[ihjsmac]#i', $date)) {
            $date = str_replace(['i', 'h', 'j', 's', 'm', 'a', 'c'],
                ['*60', '*3600', '*86400', '*604800', '*2419200', '*29030400', '*1451520000'], $date);

            eval('$date = '.$date.';');
        }

        return $date;
    }

    /**
     * @param string $type
     * @return string
     */
    protected function getExtension($type)
    {
        $ext = '.tmp';
        if (preg_match('#^dat#i', $type)) {
            $ext = '.dat';
        } elseif (preg_match('#(html|tpl)#i', $type)) {
            $ext = '.tpl';
        } elseif (preg_match('#(sql|db)#i', $type)) {
            $ext = '.sql';
        }

        return $ext;
    }
}
