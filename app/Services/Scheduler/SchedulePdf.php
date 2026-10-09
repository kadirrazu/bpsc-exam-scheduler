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
        $footer = '<table style="width:100%;font-size:7pt"><tr><td style="width:80%;border:0;text-align:center">'.__('Printed:').' '.Ui::date(now(), 'd M Y h:i A').'</td><td style="width:20%;border:0;text-align:right">'.__('Page').' {PAGENO} '.__('of').' {nbpg}</td></tr></table>';
        $pdf->SetHTMLFooter($this->fontRuns($footer));
        $html = preg_replace('/<div class="credit">.*?<\/div>/s', '', $html);
        preg_match('/<style[^>]*>(.*?)<\/style>/si', $html, $styles);
        $css = preg_replace('/@page\s*\{.*?\}/s', '', $styles[1] ?? '');
        preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $body);
        $pdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($this->fontRuns($body[1] ?? ''), HTMLParserMode::HTML_BODY);

        // CSS text opacity is not applied by mPDF. Draw the credit with true PDF alpha.
        $lastPage = $pdf->page;
        $pdf->SetAutoPageBreak(false);
        for ($page = 1; $page <= $lastPage; $page++) {
            $pdf->page = $page;
            $pdf->SetAlpha(0.65);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(12, $pdf->h - 13);
            $pdf->SetFont('dejavusans', '', 7, true, true);
            $prefix = 'Software Developed By: ';
            $pdf->Cell($pdf->GetStringWidth($prefix) + 1, 3, $prefix);
            $pdf->SetFont('dejavusans', 'B', 7, true, true);
            $pdf->Cell($pdf->GetStringWidth('IT Section, BPSC') + 1, 3, 'IT Section, BPSC');
            $pdf->SetAlpha(1);
            $pdf->SetFont('dejavusans', '', 7);
        }
        $pdf->page = $lastPage;

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
                $bold = false;
                for ($parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
                    if (preg_match('/font-weight\s*:\s*(normal|[1-9]00|bold|bolder)\b/i', $parent->getAttribute('style'), $weight)) {
                        $bold = in_array(strtolower($weight[1]), ['bold', 'bolder', '600', '700', '800', '900'], true);
                        break;
                    }
                    if (in_array(strtolower($parent->tagName), ['b', 'strong', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                        $bold = true;
                        break;
                    }
                }
                $pieces = preg_split('/([\x{0980}-\x{09FF}\x{200C}\x{200D}]+(?:[ \t]+[\x{0980}-\x{09FF}\x{200C}\x{200D}]+)*)/u', $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
                $fragment = $document->createDocumentFragment();
                foreach ($pieces as $index => $piece) {
                    if ($piece === '') { continue; }
                    $span = $document->createElement('span');
                    $span->setAttribute('style', 'font-family:'.($index % 2 === 1 ? 'nikosh' : 'dejavusans').($bold ? ';font-weight:bold' : ''));
                    $span->appendChild($document->createTextNode($piece));
                    $fragment->appendChild($span);
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
