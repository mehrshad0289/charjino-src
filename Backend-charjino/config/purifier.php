<?php

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
        ],
        'blog' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'h2[id|dir|class],h3[id|dir|class],h4[id|dir|class],p[dir|class],br,strong,b,em,i,u,ul,ol,li,blockquote[dir],a[href|rel|target|title],img[src|alt|width|height],table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],figure,figcaption,iframe[src|width|height|title|allowfullscreen],div[dir|class],code,pre,hr',
            'CSS.AllowedProperties' => '',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
            'HTML.SafeIframe' => true,
            'URI.SafeIframeRegexp' => '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/)%',
            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
            ],
            'Attr.AllowedRel' => 'nofollow,noopener,noreferrer',
            'Attr.AllowedFrameTargets' => '_blank',
            'Attr.EnableID' => true,
            'HTML.Nofollow' => false,
            'Core.EscapeInvalidTags' => true,
        ],
        'custom_definition' => [
            'id' => 'html5-definitions',
            'rev' => 2,
            'debug' => false,
            'elements' => [
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],
                ['u', 'Inline', 'Inline', 'Common'],
            ],
            'attributes' => [
                ['iframe', 'allowfullscreen', 'Bool'],
                ['a', 'rel', 'Text'],
            ],
        ],
        'custom_attributes' => [
            ['a', 'target', 'Enum#_blank,_self,_top'],
        ],
        'custom_elements' => [
            ['u', 'Inline', 'Inline', 'Common'],
        ],
    ],
];
