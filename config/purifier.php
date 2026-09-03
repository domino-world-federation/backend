<?php

/**
 * Ok, glad you are here
 * first we get a config instance, and set the settings
 * $config = HTMLPurifier_Config::createDefault();
 * $config->set('Core.Encoding', $this->config->get('purifier.encoding'));
 * $config->set('Cache.SerializerPath', $this->config->get('purifier.cachePath'));
 * if ( ! $this->config->get('purifier.finalize')) {
 *     $config->autoFinalize = false;
 * }
 * $config->loadArray($this->getConfig());
 *
 * You must NOT delete the default settings
 * anything in settings should be compacted with params that needed to instance HTMLPurifier_Config.
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            /*
             * Daftar ini DIPERLUAS dari bawaan paketnya, dan itu perbaikan
             * cacat, bukan pelonggaran.
             *
             * Bawaannya tidak memuat `h2`, `h3`, `s`, maupun `mark` — padahal
             * keempatnya adalah tombol yang memang ada di toolbar editor. Yang
             * terjadi: orang menekan "Heading 2", menyimpan, dan judulnya
             * kembali jadi paragraf tanpa satu pun pesan galat. Coret dan sorot
             * hilang sama sekali.
             *
             * `img` sudah ada di bawaannya; ia dipertahankan karena editor
             * sekarang bisa menyisipkan gambar.
             *
             * Yang sengaja TIDAK diizinkan: `script`, `iframe`, `style`,
             * `form`, dan atribut `on*` — HTML ini nanti dirender di situs
             * publik, dan editor adalah kenyamanan mengetik, bukan batas
             * keamanan.
             */
            'HTML.Allowed' => 'p[style],br,hr,h2,h3,blockquote,strong,b,em,i,u,s,mark,code,pre,ul,ol,li,a[href|title|target|rel],img[src|alt|width|height],span[style]',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty' => true,

            // Tautan keluar tidak boleh bisa menyetir tab asalnya lewat
            // `window.opener`. HTMLPurifier menambahkan `rel` sendiri kalau ini
            // menyala, jadi tidak ada yang perlu diingat penulisnya.
            'HTML.TargetBlank' => true,
            'HTML.TargetNoreferrer' => true,
            'HTML.TargetNoopener' => true,
        ],
        /*
         * Profil untuk editor DASAR — blok halaman hukum.
         *
         * Sengaja jauh lebih sempit daripada `default`. Yang dibutuhkan blok
         * hukum cuma penegasan di dalam kalimat dan daftar bernomor; judul,
         * gambar, blockquote, dan kode tidak punya tempat di sana — bloknya
         * SUDAH punya judulnya sendiri di field terpisah, dan `h2` di dalam
         * deskripsinya akan berdiri sejajar dengan judul itu.
         *
         * `style` dan `span` juga dibuang: dokumen hukum yang sebagian
         * kalimatnya berwarna adalah dokumen yang tidak bisa dicetak sama untuk
         * semua orang.
         *
         * `AutoFormat.AutoParagraph` MENYALA di sini dan itu benar — berbeda
         * dari waktu kontrolnya masih textarea polos. Editor tiptap sudah
         * mengirim isinya terbungkus `<p>`, jadi aturan itu tidak menambah apa
         * pun; ia cuma jaring untuk potongan yang masuk tanpa pembungkus.
         */
        'legal' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,b,em,i,u,s,ul,ol,li,a[href|title|target|rel]',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty' => true,
            'HTML.TargetBlank' => true,
            'HTML.TargetNoreferrer' => true,
            'HTML.TargetNoopener' => true,
        ],
        'test' => [
            'Attr.EnableID' => 'true',
        ],
        'youtube' => [
            'HTML.SafeIframe' => 'true',
            'URI.SafeIframeRegexp' => '%^(http://|https://|//)(www.youtube.com/embed/|player.vimeo.com/video/)%',
        ],
        'custom_definition' => [
            'id' => 'html5-definitions',
            'rev' => 1,
            'debug' => false,
            'elements' => [
                // http://developers.whatwg.org/sections.html
                ['section', 'Block', 'Flow', 'Common'],
                ['nav',     'Block', 'Flow', 'Common'],
                ['article', 'Block', 'Flow', 'Common'],
                ['aside',   'Block', 'Flow', 'Common'],
                ['header',  'Block', 'Flow', 'Common'],
                ['footer',  'Block', 'Flow', 'Common'],

                // Content model actually excludes several tags, not modelled here
                ['address', 'Block', 'Flow', 'Common'],
                ['hgroup', 'Block', 'Required: h1 | h2 | h3 | h4 | h5 | h6', 'Common'],

                // http://developers.whatwg.org/grouping-content.html
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],

                // http://developers.whatwg.org/the-video-element.html#the-video-element
                ['video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
                    'src' => 'URI',
                    'type' => 'Text',
                    'width' => 'Length',
                    'height' => 'Length',
                    'poster' => 'URI',
                    'preload' => 'Enum#auto,metadata,none',
                    'controls' => 'Bool',
                ]],
                ['source', 'Block', 'Flow', 'Common', [
                    'src' => 'URI',
                    'type' => 'Text',
                ]],

                // http://developers.whatwg.org/text-level-semantics.html
                ['s',    'Inline', 'Inline', 'Common'],
                ['var',  'Inline', 'Inline', 'Common'],
                ['sub',  'Inline', 'Inline', 'Common'],
                ['sup',  'Inline', 'Inline', 'Common'],
                ['mark', 'Inline', 'Inline', 'Common'],
                ['wbr',  'Inline', 'Empty', 'Core'],

                // http://developers.whatwg.org/edits.html
                ['ins', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
                ['del', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
            ],
            'attributes' => [
                ['iframe', 'allowfullscreen', 'Bool'],
                ['table', 'height', 'Text'],
                ['td', 'border', 'Text'],
                ['th', 'border', 'Text'],
                ['tr', 'width', 'Text'],
                ['tr', 'height', 'Text'],
                ['tr', 'border', 'Text'],
            ],
        ],
        'custom_attributes' => [
            ['a', 'target', 'Enum#_blank,_self,_target,_top'],
        ],
        'custom_elements' => [
            ['u', 'Inline', 'Inline', 'Common'],
        ],
    ],

];
