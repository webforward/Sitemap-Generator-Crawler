<?php
/*
Sitemap Generator by Slava Knyazev

Website: https://www.knyz.org/
I also live on GitHub: https://github.com/viruzx
Contact me: Slava@KNYZ.org
*/

//Make sure to use the latest revision by downloading from github: https://github.com/viruzx/Sitemap-Generator-Crawler

/* Usage
Usage is pretty strait forward:
- Configure the crawler
- Select the file to which the sitemap will be saved
- Select URL to crawl
- Select accepted extensions ("/" is manditory for proper functionality)
- Select change frequency (always, daily, weekly, monthly, never, etc...)
- Choose priority (It is all relative so it may as well be 1)
- Generate sitemap
- Either send a GET request to this script or simply point your browser
- A sitemap will be generated and displayed
- Submit to Google
- For better results
- Submit sitemap.xml to Google and not the script itself (Both still work)
- Setup a CRON Job to send web requests to this script every so often, this will keep the sitemap.xml file up to date

It is recommended you don't remove the above for future reference.
*/

// Add PHP CLI support
if (php_sapi_name() === 'cli') {
    parse_str(implode('&', array_slice($argv, 1)), $args);
}

$file = "sitemap.xml";
$url = "https://www.knyz.org";

$max_depth = 0;

$enable_frequency = false;
$enable_priority = false;
$enable_modified = false;

$extension = array(
    "/",
    "php",
    "html",
    "htm"
);
$freq = "daily";
$priority = "1";

$ignore_duplicate_pages = true;

/* NO NEED TO EDIT BELOW THIS LINE */

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}

function Path($p)
{
    $a = explode("/", $p);
    $len = strlen($a[count($a) - 1]);
    return (substr($p, 0, strlen($p) - $len));
}

function GetUrl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function Check($uri)
{
    global $extension;
    if (is_array($extension)) {
        $string = $uri;
        foreach ($extension as $url) {
            if (endsWith($string, $url) !== FALSE) {
                return true;
            }
        }
    }
    return false;
}

function GetUrlModified($url)
{
    $hdr = get_headers($url, 1);
    if (!empty($hdr['Last-Modified'])) {
        return date('c', strtotime($hdr['Last-Modified']));
    } else {
        return false;
    }
}

function Scan($url)
{
    global $scanned, $pf, $freq, $priority, $enable_modified, $enable_priority, $enable_frequency, $max_depth, $depth, $page_contents, $ignore_duplicate_pages;
    array_push($scanned, $url);
    $depth++;

    if (isset($max_depth) && ($depth <= $max_depth || $max_depth == 0)) {

        $html = GetUrl($url);
        if (isset($ignore_duplicate_pages) && $ignore_duplicate_pages == true) $hash = md5($html);

        if ($enable_modified) $modified = GetUrlModified($url);


        $regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
        if (preg_match_all("/$regexp/siU", $html, $matches)) {
            if ($matches[2]) {
                $links = $matches[2];
                unset($matches);
                foreach ($links as $href) {


                    if ((substr($href, 0, 7) != "http://") && (substr($href, 0, 8) != "https://") && (substr($href, 0, 6) != "ftp://")) {
                        // If href does not starts with http:, https: or ftp:
                        if ($href == '/') {
                            $href = $scanned[0] . $href;
                        } else {
                            $href = Path($url) . $href;
                        }
                    }

                    if (substr($href, 0, strlen($scanned[0])) == $scanned[0]) {
                        // If href is a sub of the scanned url

                        if (isset($hash) && key_exists($hash, $page_contents)) {
                            $ignore = true;
                        }
                        if (isset($hash)) array_push($page_contents, isset($page_contents[$hash])?$page_contents[$hash]+1:1);

                        if (!isset($ignore) && (!in_array($href, $scanned)) && Check($href)) {

                            $map_row = "<url>\n";
                            $map_row .= "<loc>$href</loc>\n";
                            if ($enable_frequency) $map_row .= "<changefreq>$freq</changefreq>\n";
                            if ($enable_priority) $map_row .= "<priority>$priority</priority>\n";
                            if (!empty($modified)) $map_row .= "   <lastmod>$modified</lastmod>\n";
                            $map_row .= "</url>\n";

                            fwrite($pf, $map_row);


                            echo "Added: " . $href . ((!empty($modified)) ? " [Modified: " . $modified . "]" : '') . "\n";

                            Scan($href);
                        }
                    }

                }
            }
        }
    }
    $depth--;
}

if (isset($args['file'])) $file = $args['file'];
if (isset($args['url'])) $url = $args['url'];

if (endsWith($url, '/')) $url = substr(0, strlen($url) - 1);

$start = microtime(true);
$pf = fopen($file, "w");
if (!$pf) {
    echo "Error: Could not create file - $file\n";
    exit;
}
fwrite($pf, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<urlset
      xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"
      xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
      xsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9
            http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd\">
<url>
  <loc>$url/</loc>
  " . ($enable_frequency ? "<changefreq>daily</changefreq>\n" : '') . "</url>
");
$depth = 0;
$page_contents = array();
$scanned = array();
Scan($url);
fwrite($pf, "</urlset>\n");
fclose($pf);
$time_elapsed_secs = microtime(true) - $start;
echo "Sitemap has been generated in " . $time_elapsed_secs . " second" . ($time_elapsed_secs >= 1 ? 's' : '') . ".\n";
print_r($page_contents);
?>