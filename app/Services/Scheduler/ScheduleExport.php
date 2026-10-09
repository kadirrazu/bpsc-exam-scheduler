<?php

namespace App\Services\Scheduler;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ScheduleExport
{
    public function response(string $format, Collection $schedules, array $filters)
    {
        $filename = 'BPSC-Schedules-'.($filters['from'] ? $filters['from'].'-to-'.$filters['to'] : 'All-Dates').'-'.now()->format('Ymd-His');
        $details = ['report' => 'exam_schedule_list', 'report_name' => 'Exam Schedule List', 'format' => $format, 'filters' => $filters, 'row_count' => $schedules->count(), 'locale' => app()->getLocale(), 'generated_at' => now()->toIso8601String()];
        if ($format === 'print') {
            $reportToken = Crypt::encryptString(json_encode($details + ['actor_id' => request()->user()->id, 'expires_at' => now()->addMinutes(30)->timestamp], JSON_THROW_ON_ERROR));
            // Render first: a Blade failure must never be reported as a successful report view.
            $html = view('schedules.report', compact('schedules', 'filters', 'reportToken') + ['printing' => true])->render();
            app(Audit::class)->record('report.viewed', $details);

            return response($html);
        }
        if ($format === 'pdf') {
            $bytes = app(SchedulePdf::class)->render(view('schedules.report', compact('schedules', 'filters') + ['printing' => false])->render());
            app(Audit::class)->record('report.generated', $details + ['filename' => $filename.'.pdf']);

            return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"']);
        }
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Exam Schedules');
        $grouped = ($filters['display'] ?? 'flat') === 'grouped';
        $headers = array_map(fn ($s) => __($s), ['#', 'Date', 'Unit / Exam Type', 'Post Name', 'Post Code', 'Advertisement Number', 'Advertisement Year', 'Time', 'Candidates', 'Centers', 'Boards', 'Status', 'Notes/Remarks', 'Ministry/Organization', 'Post Grade', 'Number of Vacant Posts']);
        if ($grouped) [$headers[0], $headers[1]] = [$headers[1], $headers[0]];
        $sheet->setCellValue('A1', __('Bangladesh Public Service Commission (BPSC)'));
        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A2', __('Exam Schedules').': '.($filters['from'] ? $filters['from'].' — '.$filters['to'] : __('All dates')));
        $sheet->mergeCells('A2:P2');
        $sheet->fromArray($headers, null, 'A4');
        $groupStart = 5;
        $previousDate = null;
        $dateSerial = 0;
        foreach ($schedules as $i => $s) {
            $date = $s->exam_date->format('Y-m-d');
            $rowNumber = $i + 5;
            if ($grouped && $previousDate !== null && $date !== $previousDate) {
                if ($rowNumber - 1 > $groupStart) $sheet->mergeCells('A'.$groupStart.':A'.($rowNumber - 1));
                $groupStart = $rowNumber;
            }
            $dateSerial = $date === $previousDate ? $dateSerial + 1 : 1;
            $previousDate = $date;
            $row = [$i + 1, $date, $s->unit."\n".$s->typeLabel(), $s->post_name ?? $s->title, $s->reference, $s->advertisement_number, $s->advertisement_year, $s->timeLabel(), $s->candidate_count, $s->center_count, ($s->isViva() ? ($s->board_structure ? $s->boardsLabel() : $s->board_count) : null), __(config('scheduler.statuses.'.$s->status)), $s->notes, $s->ministry, $s->post_grade, $s->vacant_posts];
            if ($grouped) [$row[0], $row[1]] = [$date, $dateSerial];
            $sheet->getStyle('L'.$rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(config('scheduler.status_colors.'.$s->status, 'F1F3F5'));
            foreach ($row as $col => $value) {
                if ($col === 2) {
                    $text = new RichText;
                    $text->createTextRun($s->unit)->getFont()->setBold(true)->getColor()->setRGB('1D4ED8');
                    $text->createTextRun("\n--------\n")->getFont()->getColor()->setRGB('CBD5E1');
                    $text->createTextRun($s->typeLabel())->getFont()->setBold(true)->getColor()->setRGB('166534');
                    $sheet->setCellValue([$col + 1, $rowNumber], $text);
                    continue;
                }
                $sheet->setCellValueExplicit([$col + 1, $rowNumber], $value ?? '', is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
        }
        $last = max(4, $schedules->count() + 4);
        if ($grouped && $last > $groupStart) $sheet->mergeCells('A'.$groupStart.':A'.$last);
        $sheet->getStyle('A1:P4')->getFont()->setBold(true);
        $sheet->getStyle('A4:P'.$last)->getAlignment()->setWrapText(true)->setVertical('center')->setHorizontal('center');
        foreach (['D','M','N'] as $column) $sheet->getStyle($column.'4:'.$column.$last)->getAlignment()->setHorizontal('left');
        $sheet->getStyle('A4:P'.$last)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range('A', 'P') as $col) $sheet->getColumnDimension($col)->setWidth(in_array($col, ['D','M','N']) ? 35 : ($col === 'H' ? 28 : (in_array($col, ['C','K']) ? 24 : 16)));
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(4, 4);
        $sheet->freezePane('A5');
        if (($filters['display'] ?? 'flat') === 'flat') $sheet->setAutoFilter('A4:P'.$last);
        // Complete XLSX generation before logging success or sending any bytes. Never log failed generation as success.
        $temp = tmpfile();
        if (! $temp) {
            throw new \RuntimeException('Cannot create spreadsheet temporary file.');
        }
        try {
            (new Xlsx($book))->save(stream_get_meta_data($temp)['uri']);
            rewind($temp);
            $bytes = stream_get_contents($temp);
        } finally {
            fclose($temp);
            $book->disconnectWorksheets();
        }
        app(Audit::class)->record('report.generated', $details + ['filename' => $filename.'.xlsx']);

        return response($bytes, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"']);
    }
}
