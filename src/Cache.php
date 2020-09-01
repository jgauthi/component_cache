<?php
/*******************************************************************************
 * @name: Cache
 * @note: Data storage in the form of temporary files (cache)
 * @author: Jgauthi <github.com/jgauthi>, created at [14mars2009]
 * @version 2.0

 *******************************************************************************/

namespace Jgauthi\Component\Cache;

use InvalidArgumentException;

class Cache
{
    public const TYPE_DATA = 'data';
    public const TYPE_TEMPLATE = 'template';
    public const TYPE_SQL = 'sql';
    public const TYPE_FILE = 'file';

    public string $cacheName;
    public string $folder;
    public string $date;
    protected string $cacheFile;
    protected string $type;
    protected ?bool $cacheExists = null;
    /** @var mixed $content */
    protected $content;

    public function __construct(string $cacheName, string $date, string $folder = 'tmp', string $type = self::TYPE_TEMPLATE)
    {
        // Type de cache
        $this->type = self::TYPE_FILE;
        if (preg_match('#^dat#i', $type)) {
            $this->type = self::TYPE_DATA;
        } elseif (preg_match('#(html|tpl|template)#i', $type)) {
            $this->type = self::TYPE_TEMPLATE;
        } elseif (preg_match('#(sql|db)#i', $type)) {
            $this->type = self::TYPE_SQL;
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

    public function exists(): ?bool
    {
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
     * @return mixed
     */
    public function content(?string $content = null)
    {
        if (in_array($this->type, [self::TYPE_DATA, self::TYPE_SQL]) && (!is_array($content) && !is_object($content))) {
            $this->content = unserialize(file_get_contents($this->cacheFile));
        } elseif (self::TYPE_TEMPLATE === $this->type && $this->cacheExists) {
            $this->content = file_get_contents($this->cacheFile);
        } elseif (self::TYPE_TEMPLATE === $this->type && !empty($content)) {
            $this->content = $content;
        } elseif (!empty($content)) {
            $this->content = $content;
        }

        return $this->content;
    }

    public function create(?string $content = null): bool
    {
        if (empty($content)) {
            if (empty($this->content)) {
                throw new InvalidArgumentException('Could not create cache, no content');
            }
            $content = $this->content;
        }

        // Signature
        switch ($this->type) {
            case self::TYPE_TEMPLATE:
                $content .= "\n\n".'<!-- CACHE {'.$this->cacheName.'} time: '.date('d-m-Y, H:i:s').' -->'."\n\n";
                break;

            case self::TYPE_DATA:
            case self::TYPE_SQL:
                $content = serialize($content);
                break;

            default:
                throw new InvalidArgumentException('Cache Data ['.$this->type.'] id: {'.$this->cacheName.'} corrupt');
        }

        // Cache creation
        $file = @fopen($this->cacheFile, 'w');
        fwrite($file, $content);
        fclose($file);

        return file_exists($this->cacheFile);
    }

    public function delete(?string $filename = null, string $type = 'tpl'): bool
    {
        if (is_null($filename)) {
            $file = $this->cacheFile;
        } else {
            $file = $this->folder.md5($filename).$this->getExtension($type);
        }

        return @unlink($file);
    }

    public function clearCache(?string $folder = null): int
    {
        if (null === $folder) {
            $folder = $this->folder;
        }

        // Vérifier le dossier
        if (!is_dir($folder)) {
            throw new InvalidArgumentException("Folder {$folder} doesn't exists or is empty.");
        }

        $dir = opendir($folder);
        $nb_file_delete = 0;
        while ($file = readdir($dir)) {
            if ('.' === $file && '..' === $file) {
                continue;
            }

            $ext = $this->getExtension($this->type);

            // Clear cache files
            if (preg_match("#^cache_([a-f0-9]{32})\.".$ext.'$#i', $file) && @unlink($file)) {
                ++$nb_file_delete;
            }
        }
        closedir($dir);

        return $nb_file_delete;
    }

    protected function calculTime(string $date): int
    {
        if (preg_match('#[ihjsmac]#i', $date)) {
            $date = str_replace(['i', 'h', 'j', 's', 'm', 'a', 'c'],
                ['*60', '*3600', '*86400', '*604800', '*2419200', '*29030400', '*1451520000'], $date);

            eval('$date = '.$date.';');
        }

        return $date;
    }

    protected function getExtension(string $type): string
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
