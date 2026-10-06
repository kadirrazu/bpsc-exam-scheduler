<?php

namespace App\Services\Scheduler;

use App\Support\Ui;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

class SchedulePdf
{
    public function render(string $html): string
    {
        $defaults = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();
        $temp = storage_path('app/scheduler-pdf-cache');
        if (! is_dir($temp) && ! mkdir($temp, 0755, true) && ! is_dir($temp)) {
            throw new \RuntimeException('Cannot create PDF temporary directory.');
        }
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'orientation' => 'L', 'tempDir' => $temp,
            'fontDir' => array_merge($defaults['fontDir'], [public_path('fonts')]),
            'fontdata' => $fonts['fontdata'] + ['nikosh' => ['R' => 'Nikosh.ttf', 'useOTL' => 0xFF]],
            'default_font' => 'dejavusans', 'default_font_size' => 9,
            'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 14, 'margin_bottom' => 18, 'margin_footer' => 8,
        ]);
        $pdf->SetTitle(__('Examination Schedule'));
        $pdf->SetAuthor(__('Bangladesh Public Service Commission (BPSC)'));
        $footer = '<table style="width:100%;font-size:8px"><tr><td style="border:0">'.__('Software Developed By:').' <b>'.__('IT Section, BPSC').'</b> | '.__('Printed:').' '.Ui::date(now(), 'd M Y h:i A').'</td><td style="border:0;text-align:right">'.__('Page').' {PAGENO} '.__('of').' {nbpg}</td></tr></table>';
        $pdf->SetHTMLFooter($this->fontRuns($footer));
        $html = preg_replace('/<div class="credit">.*?<\/div>/s', '', $html);
        preg_match('/<style[^>]*>(.*?)<\/style>/si', $html, $styles);
        $css = preg_replace('/@page\s*\{.*?\}/s', '', $styles[1] ?? '');
        preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $body);
        $pdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($this->fontRuns($body[1] ?? ''), HTMLParserMode::HTML_BODY);

        return $pdf->Output('', 'S');
    }

    /** Reused Choice PDF text-node shaping: Bengali OTL fonts, escaped text and Latin font preservation. */
    public function fontRuns(string $html): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET);
            $xpath = new \DOMXPath($document);
            $nodes = [];
            foreach ($xpath->query('//body//text()[not(ancestor::script) and not(ancestor::style)]') as $node) {
                $nodes[] = $node;
            }
            foreach ($nodes as $node) {
                $pieces = preg_split('/([\x{0980}-\x{09FF}\x{200C}\x{200D}]+(?:[ \t]+[\x{0980}-\x{09FF}\x{200C}\x{200D}]+)*)/u', $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
                if (count($pieces) < 2) {
                    continue;
                }
                $fragment = $document->createDocumentFragment();
                foreach ($pieces as $index => $piece) {
                    if ($index % 2 === 1) {
                        $span = $document->createElement('span');
                        $span->setAttribute('style', 'font-family:nikosh');
                        $span->appendChild($document->createTextNode($piece));
                        $fragment->appendChild($span);
                    } else {
                        $fragment->appendChild($document->createTextNode($piece));
                    }
                }
                $node->parentNode->replaceChild($fragment, $node);
            }
            $result = '';
            foreach ($document->getElementsByTagName('body')->item(0)->childNodes as $child) {
                $result .= $document->saveHTML($child);
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
