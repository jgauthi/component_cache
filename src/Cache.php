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
    public $nom;
    public $dir;
    public $date;
    protected $fichier;
    protected $contenu;
    protected $type;
    protected $etat = null;

    /**
     * cache constructor.
     *
     * @param string $nom
     * @param string $date
     * @param string $dossier
     * @param string $type
     */
    public function __construct($nom, $date, $dossier = 'tmp', $type = 'tpl')
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

        // Tentative de créer le dossier
        if (!is_dir($dossier) && !mkdir($dossier, 0777)) {
            throw new InvalidArgumentException("Impossible de créer le dossier '$dossier', la class ".__CLASS__.' ne peut pas fonctionner');
        }

        $this->dir = $dossier.'/';

        // Info du cache
        $this->nom = md5($nom.$_SERVER['HTTP_HOST']);
        $this->date = $this->_date($date);
        $this->fichier = $this->dir.'cache_'.$this->nom.$this->_extension($this->type);
    }

    //-- FONCTIONS ----------------------------------------------------------

    /**
     * @return bool|null
     */
    public function existe()
    {
        // Si le cache existe
        if (null === $this->etat) {
            $this->etat = false;

            if (is_readable($this->fichier)) {
                if (@filemtime($this->fichier) > (time() - $this->date)) {
                    $this->etat = true;
                } else {
                    $this->detruire();
                }
            }
        }

        return $this->etat;
    }

    /**
     * Gestionnaire de contenu.
     *
     * @param string $contenu
     *
     * @return array|false|mixed|string
     */
    public function contenu($contenu = '')
    {
        if (('data' === $this->type || 'sql' === $this->type) && (!is_array($contenu) && !is_object($contenu))) {
            $this->contenu = unserialize(file_get_contents($this->fichier));
        } elseif ('template' === $this->type && $this->etat) {
            $this->contenu = file_get_contents($this->fichier);
        } elseif ('template' === $this->type && !empty($contenu)) {
            $this->contenu = $contenu;
        } elseif (!empty($contenu)) {
            $this->contenu = $contenu;
        }

        // Envoyer le contenu si $contenu = TRUE
        return $this->contenu;
    }

    /**
     * Création du cache en indiquant une valeur si contenu() n'a pas été utilisé.
     *
     * @param string $contenu
     *
     * @return bool
     */
    public function creer($contenu = '')
    {
        if (empty($contenu)) {
            if (empty($this->contenu)) {
                throw new InvalidArgumentException('Impossible de créer le cache, pas de contenu');
            }
            $contenu = $this->contenu;
        }

        // Signature
        switch ($this->type) {
            case 'template':
                $contenu .= "\n\n".'<!-- CACHE {'.$this->nom.'} time: '.date('d-m-Y, H:i:s').' -->'."\n\n";
                break;

            case 'data': case 'sql':
                $contenu = serialize($contenu);
                break;

            default:
                throw new InvalidArgumentException('Donné du Cache ['.$this->type.'] id: {'.$this->nom.'} corrompu');
        }

        // Création du cache
        $fichier = @fopen($this->fichier, 'w');
        fwrite($fichier, $contenu);
        fclose($fichier);

        return file_exists($this->fichier);
    }

    /**
     * Effacer un cache.
     *
     * @param null   $fichier
     * @param string $type
     *
     * @return bool
     */
    public function detruire($fichier = null, $type = 'tpl')
    {
        if (null === $fichier) {
            $fichier = $this->fichier;
        } else {
            $fichier = $this->dir.md5($fichier).$this->_extension($type);
        }

        return @unlink($fichier);
    }

    /**
     * Clean cache (supprimer tous les caches crée par cette class).
     *
     * @param string|null $dossier
     *
     * @return bool|int
     */
    public function effacer_cache($dossier = null)
    {
        if (null === $dossier) {
            $dossier = $this->dir;
        }

        // Vérifier le dossier
        if (is_dir($dossier)) {
            $dir = opendir($dossier);
            $nb_file_delete = 0;
            while ($file = readdir($dir)) {
                if ('.' === $file && '..' === $file) {
                    continue;
                }

                $ext = $this->_extension($this->type);

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

    //-- Sous fonctions -------------------------------------------------------

    /**
     * @param string $date
     *
     * @return mixed
     */
    protected function _date($date)
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
     *
     * @return string
     */
    protected function _extension($type)
    {
        if (preg_match('#^dat#i', $type)) {
            $ext = '.dat';
        } elseif (preg_match('#(html|tpl)#i', $type)) {
            $ext = '.tpl';
        } elseif (preg_match('#(sql|db)#i', $type)) {
            $ext = '.sql';
        } else {
            $ext = '.tmp';
        }

        return $ext;
    }
}
