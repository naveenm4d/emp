<?php

namespace App\Domains\Template\Support;

/** A neutral labelled picture for image slots nobody has filled yet (previews and the editor). */
final class PlaceholderImage
{
    public static function dataUri(string $label): string
    {
        $label = htmlspecialchars($label, ENT_QUOTES | ENT_XML1);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e7e5e4"/><stop offset="1" stop-color="#a8a29e"/></linearGradient></defs>'
            .'<rect width="1200" height="800" fill="url(#g)"/>'
            ."<text x=\"600\" y=\"410\" font-family=\"sans-serif\" font-size=\"44\" fill=\"#57534e\" text-anchor=\"middle\">{$label}</text></svg>";

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
