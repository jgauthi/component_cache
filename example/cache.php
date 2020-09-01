<?php
// In this example, the vendor folder is located in "example/"
use Jgauthi\Component\Cache\Cache;

require_once __DIR__.'/vendor/autoload.php';

//-- Configuration ----------------------
$url = 'http://fr.wikipedia.org/wiki/Bombardements_atomiques_de_Hiroshima_et_Nagasaki';
$debut = '<a href="/wiki/Fichier:Atomic_cloud_over_Hiroshima.jpg"';
$fin = '<table class="plainlinks" id="article_de_qualite"';
define('TMP_PATH', sys_get_temp_dir()); // You can set your tmp folder

//---------------------------------------


$data = new Cache($url, '5i', TMP_PATH); // 5min

if ($data->exists()) {
    $content = $data->content();

} else {
    $stripBody = function (string $html): string {
        if (preg_match("/<body[^>]*>(.*?)<\/body>/is", $html, $row)) {
            return $row[1];
        }

        return $html;
    };

    $page = file_get_contents($url);
    $page = preg_replace("#^(.+){$debut}#i", $debut, $page);
    $page = preg_replace("#{$fin}(.+)$#i", '', $page);
    $page = preg_replace('#src="/#Di', 'src="http://fr.wikipedia.org/', $page);
    $page = $stripBody($page);
    $content = $data->content($page);
}

?>
<style type="text/css">
    .contenu
    {
        width: 75%;
        margin-left: auto;
        margin-right: auto;
        padding: 15px;
        border: 1px dashed black;
        background-color: #ccc;
        font-size: 0.9em;
    }
</style>

<h1>Downloading a page and caching</h1>
<div class="contenu"><?=$content?></div>
