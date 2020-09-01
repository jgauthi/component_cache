<?php
/**
 * Cache - Data storage in the form of temporary files (cache)
 * @author Jgauthi <github.com/jgauthi>
 */

class Cache
{
    // Class var
    var $nom;
    var $date;
    var $fichier;
    var $contenu;
    var $etat;
    var $dir;

    /*-- CONSTRUCTEUR -----------------------------------------------------*/
    FUNCTION cache($nom, $date, $dossier = 'tmp/', $type = 'tpl')
    {
        // Type de cache
        if 	(eregi("^dat",$type)) 			{	$this -> type = 'data';		}
        elseif (eregi("(html|tpl)",$type))	{	$this -> type = 'template';	}
        elseif (eregi("(sql|db)",$type))	{	$this -> type = 'sql';		}
        else								{	$this -> type = 'file';		}

        // Configuration dossier temporaire
        if (!file_exists($dossier) || !is_dir($dossier))
        {
            // Tentative de créer le dossier
            if (!mkdir($dossier, 0777))
            {
                user_error(	'Impossible de créer le dossier "'. $dossier .
                    '", la class cache ne peut pas fonctionner.');
                return (die());
            }
        }
        $this -> dir = $dossier;

        // Info du cache
        $this -> nom = md5($nom . 'AllanTK @ PHP');
        $this -> date = $this -> _date($date);
        $this -> fichier = $this -> dir.'cache_'.$this -> nom . $this -> _extension($this -> type);

        // Stocker la liste des caches utilisés si DEBUG
        //$_LOG['cache'][] = $this -> nom.$ext; // Fonction Akila
    }

    //-- FONCTIONS ----------------------------------------------------------
    FUNCTION existe()
    {
        // Mini cache
        if (!empty($this -> etat))
        {
            if ($this -> etat == 'ok')	return TRUE;
            else						return FALSE;
            exit();
        }

        // Si le cache existe
        if (@is_file($this -> fichier))
        {
            if (@filemtime($this -> fichier) > time()- $this -> date)
            {
                $this -> etat = 'ok';
                return TRUE;
                exit();
            }
            else
            {
                $this -> detruire();
                $this -> etat = 'non';
            }
        }
        else	$this -> etat = 'non';
        return FALSE;
    }

    // Gestionnaire de contenu
    FUNCTION contenu($contenu = '')
    {
        if (($this -> type == 'data' || $this -> type == 'sql') && (!is_array($contenu) && !is_object($contenu)))
            $this -> contenu = unserialize(file_get_contents($this -> fichier));
        elseif ($this -> type == 'template' && $this -> etat == 'ok')
            $this -> contenu = file_get_contents($this -> fichier);
        elseif ($this -> type == 'template' && !empty($contenu))
            $this -> contenu = $contenu;
        /*		elseif ($this -> type == 'template' && is_object($site)) // vieille condition avec $site (akila)
                                                $this -> contenu = $site -> OutPut();*/
        elseif (!empty($contenu))		$this -> contenu = $contenu;

        // Envoyer le contenu si $contenu = TRUE
        return ($this -> contenu);
    }

    // Création du cache en indiquant une valeur si contenu() n'a pas été utilisé
    FUNCTION creer($contenu = '')
    {
        if (empty($contenu))
        {
            if (empty($this -> contenu))
                return (user_error('Impossible de créer le cache, pas de contenu'));
            else	$contenu = $this -> contenu;
        }

        // Signature
        switch($this -> type)
        {
            case 'template':
                $contenu .= "\n\n".'<!-- ATK-CACHE {'.$this -> nom.'} time: '.date("d-m-Y, H:i:s").' -->'."\n\n";
                break;


            case 'data': case 'sql' :
            $contenu = serialize($contenu);
            break;


            default:
                return (user_error('Donné du Cache ['.$this -> type.'] id: {'.$this -> nom.'} corrompu'));
                break;
        }

        // Création du cache
        $fichier = @fopen($this -> fichier,'w');
        fputs($fichier,$contenu);
        fclose($fichier);
    }

    // Effacer un cache
    FUNCTION detruire ($fichier = 'self', $type = 'tpl')
    {
        if ($fichier == 'self')
            $fichier = $this -> fichier;
        else	$fichier = $this -> dir.md5($fichier) . $this -> _extension($type);

        @unlink($fichier);
    }

    // Clean cache (supprimer tous les caches crée par cette class)
    function effacer_cache($dossier = 'tmp/')
    {
        if (empty($dossier))
            $dossier = $this -> dir;

        // Vérifier le dossier
        if (file_exists($dossier) && is_dir($dossier))
        {
            $dir = opendir($dossier);
            while ($file = readdir($dir))
            {
                $ext = $this -> _extension($this -> type);

                // Effacer les fichiers cache
                if ($file != "." && $file != ".." && eregi("^cache_([a-f0-9]{32})\.".$ext."$", $file))
                    @unlink($file);
            }
            closedir($dir);
        }
        else	return (false);
    }

    //-- FONCTIONS SPECIFIQUE -------------------------------------------------

    //-- Sous fonctions -------------------------------------------------------
    FUNCTION _date($date)
    {
        if (eregi("[ihjsmac]",$date))
        {
            $date = str_replace(array('i','h','j','s','m','a','c'),
                array('*60','*3600','*86400','*604800','*2419200','*29030400','*1451520000'),$date);

            eval('$date = '.$date.';');
        }
        return $date;
    }

    function _extension($type)
    {
        // Type de cache
        if 	(eregi("^dat",$type)) 			$ext = '.dat';
        elseif (eregi("(html|tpl)",$type))	$ext = '.tpl';
        elseif (eregi("(sql|db)",$type))	$ext = '.sql';
        else								$ext = '.tmp';

        return ($ext);
    }
}
