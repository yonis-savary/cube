<?php

// Dev tool to generate markdown menu at the begining/end of doc files

function getPrettyName(string|false $rawFileName): array|false
{
    if ($rawFileName === false)
        return ['./README.md', 'Readme'];

    $filename = pathinfo($rawFileName, PATHINFO_FILENAME);
    $filename = preg_replace("/^\d+\-/", "", $filename);
    $filename = str_replace("-", " ", $filename);
    return [$rawFileName, ucfirst(strtolower($filename))];
}

function getLink(array|false $fileLink, string $style, string $label): string
{
    if (!$fileLink)
        return "";

    list($rawFileName, $prettyName) = $fileLink;
    $label = $label ? "$label :": "";

    return "<div style=\"$style\"><a href=\"$rawFileName\">$label $prettyName</a></div>";
}

$files = glob('./*.md');
$files = array_diff($files, ['./README.md']);

$readmeLink = getLink(getPrettyName("./README.md"), "Center", "");

for ($i=0; $i<count($files); $i++)
{
    $file = $files[$i];

    $previous = getLink(getPrettyName($files[$i-1] ?? false), "text-align: left", "Previous");
    $next = getLink(getPrettyName($files[$i+1] ?? false), "text-align: right", "Next");
    $delimiter = "<!-- menu -->";

    list($filename, $prettyName) = getPrettyName($file);

    echo "- [$prettyName]($filename)\n";

    $content = file_get_contents($file);

    $content = preg_replace("/^<!\-\- menu \-\->.+?\n/m", "", $content);

    $menu = "$delimiter<table style='width:100%'><tr><td style='width: 33%'>$previous</td><td style='width: 33%; text-align: center'>$readmeLink</td><td style='width: 33%'>$next</td></tr></table>\n";

    $content = $menu .  $content . $menu ;

    file_put_contents($file, $content);
}