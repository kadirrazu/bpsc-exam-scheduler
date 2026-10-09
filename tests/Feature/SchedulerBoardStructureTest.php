<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ExamSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SchedulerBoardStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $user = User::factory()->create(['role'=>UserRole::Editor,'preferred_locale'=>'en']);
        $this->actingAs($user)->withSession(['auth_version'=>$user->auth_version]);
    }

    private function data(array $extra = []): array
    {
        return array_replace(['post_name'=>'Director','ministry'=>'BPSC','unit'=>'Unit 01','exam_type'=>'viva',
            'exam_date'=>'2026-10-09','start_time'=>'10:00','status'=>'scheduled'], $extra);
    }

    private function structure(): array
    {
        return [['candidates_per_board'=>'১৫','boards'=>'৩'],['candidates_per_board'=>'১২','boards'=>'৪']];
    }

    public function test_structure_derives_totals_accepts_manual_overrides_and_audits_new_fields(): void
    {
        $this->post('/schedules',$this->data(['board_structure'=>$this->structure(),'vacant_posts'=>'০']))->assertRedirect();
        $s = ExamSchedule::firstOrFail();
        $this->assertSame(7,$s->board_count);
        $this->assertSame(93,$s->candidate_count);
        $this->assertSame(0,$s->vacant_posts);
        $this->assertNull($s->end_time);
        $this->assertSame('15',$s->board_structure[0]['candidates_per_board']);
        $this->put('/schedules/'.$s->id,$this->data(['version'=>1,'board_structure'=>$this->structure(),
            'candidate_count'=>100,'board_count'=>8,'vacant_posts'=>5]))->assertRedirect();
        $this->assertSame(100,$s->fresh()->candidate_count);
        $this->assertSame(8,$s->fresh()->board_count);
        foreach (['/schedules?display=grouped','/schedules/export/print?display=grouped'] as $url) {
            $html = $this->get($url)->assertOk()->assertSee('Unit / Exam Type')->getContent();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            $cell = '//table[thead]/tbody/tr[1]/td[8]';
            $this->assertSame(2,$xpath->query($cell.'/br')->length);
            $this->assertSame('Total Boards: 8',trim($xpath->evaluate('string('.$cell.'/strong)')));
            $this->assertSame(1,$xpath->query('//table[thead]/thead/tr/th[contains(.,"Unit / Exam Type")]')->length);
        }
        $audit = AuditLog::where('action','examschedule.updated')->where('subject_id',$s->id)->latest('id')->firstOrFail();
        $this->assertSame(5,$audit->details['after']['vacant_posts']);
        $this->assertArrayHasKey('board_structure',$audit->details['after']);
        $this->get('/schedules/'.$s->id)->assertOk()->assertSee('15 × 3 boards + 12 × 4 boards')->assertSee('10:00 AM');
        $form = $this->get('/schedules/'.$s->id.'/edit')->assertOk()->assertSee('data-end-time  hidden',false)->getContent();
        $this->assertLessThan(strpos($form,'id="candidate_count"'), strpos($form,'data-board-structure'));
        $this->put('/schedules/'.$s->id,$this->data(['version'=>2,'board_count'=>8,'board_structure'=>null]))->assertRedirect();
        $this->assertNull($s->fresh()->board_structure);
        $this->assertNull($s->fresh()->vacant_posts);
    }

    public function test_structure_and_viva_end_time_are_validated_without_overwriting_manual_counts(): void
    {
        $this->post('/schedules',$this->data(['board_count'=>1,'end_time'=>'11:00']))->assertSessionHasErrors('end_time');
        $this->post('/schedules',$this->data(['exam_type'=>'written','board_structure'=>$this->structure()]))->assertSessionHasErrors('board_structure');
        foreach ([[['boards'=>2]],[['candidates_per_board'=>15,'boards'=>0]],[['candidates_per_board'=>[], 'boards'=>2]],
            [['candidates_per_board'=>10000000,'boards'=>2]]] as $rows) {
            $this->post('/schedules',$this->data(['board_count'=>1,'candidate_count'=>1,'board_structure'=>$rows]))->assertSessionHasErrors();
        }
        $this->post('/schedules',$this->data(['board_count'=>2,'vacant_posts'=>-1]))->assertSessionHasErrors('vacant_posts');
        $this->post('/schedules',$this->data(['board_count'=>2,'board_structure'=>[['candidates_per_board'=>'','boards'=>'']]]))->assertRedirect();
        $this->assertNull(ExamSchedule::first()->board_structure);
        $this->withSession(['_old_input'=>['exam_type'=>'viva','board_structure'=>'invalid']])->get('/schedules/create')->assertOk();
    }

    public function test_grouped_dates_are_complete_and_exports_merge_only_date_cells(): void
    {
        for ($i=0;$i<30;$i++) ExamSchedule::create($this->data(['title'=>'Row '.$i,'board_count'=>2]));
        ExamSchedule::create($this->data(['title'=>'Other date','exam_date'=>'2026-10-10','board_count'=>2,'board_structure'=>$this->structure()]));
        $this->get('/schedules')->assertOk()->assertViewHas('schedules',fn($p)=>$p->count()===25);
        $grouped = $this->get('/schedules?display=grouped')->assertOk()->assertSee('rowspan="30"',false)
            ->assertViewHas('schedules',fn($rows)=>$rows->count()===31)
            ->assertViewHas('pagination',fn($p)=>$p->total()===2);
        $document = new \DOMDocument;
        @$document->loadHTML($grouped->getContent());
        $xpath = new \DOMXPath($document);
        $table = '//table[contains(@class,"schedule-table")]';
        $this->assertSame('Date',trim($xpath->evaluate('string('.$table.'/thead/tr/th[1])')));
        $this->assertSame('#',trim($xpath->evaluate('string('.$table.'/thead/tr/th[2])')));
        foreach ([1=>['td'=>2,'serial'=>'1'],2=>['td'=>2,'serial'=>'1'],3=>['td'=>1,'serial'=>'2'],31=>['td'=>1,'serial'=>'30']] as $row=>$expected) {
            $this->assertSame($expected['serial'], trim($xpath->evaluate('string('.$table.'/tbody/tr['.$row.']/td['.$expected['td'].'])')));
        }
        $this->get('/schedules/export/print?display=grouped')->assertOk()->assertSee('rowspan="30"',false);
        $pdf = $this->get('/schedules/export/pdf?display=grouped')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertMatchesRegularExpression('/\/ca\s+0\.65(?:0|\s)/',$pdf->getContent());
        $bytes=$this->get('/schedules/export/xlsx?display=grouped')->assertOk()->getContent();
        $tmp=tempnam(sys_get_temp_dir(),'board-export');
        try {
            file_put_contents($tmp,$bytes);$book=IOFactory::load($tmp);$sheet=$book->getActiveSheet();
            $this->assertArrayHasKey('A6:A35',$sheet->getMergeCells());
            $this->assertSame('Date',$sheet->getCell('A4')->getValue());
            $this->assertSame('#',$sheet->getCell('B4')->getValue());
            $this->assertSame(1,$sheet->getCell('B5')->getValue());
            $this->assertSame(1,$sheet->getCell('B6')->getValue());
            $this->assertSame(30,$sheet->getCell('B35')->getValue());
            $this->assertSame('Unit / Exam Type',$sheet->getCell('C4')->getValue());
            $this->assertSame('Time',$sheet->getCell('H4')->getValue());
            $this->assertSame('10:00 AM',$sheet->getCell('H5')->getValue());
            $this->assertSame("Unit 01\n--------\nViva",$sheet->getCell('C5')->getValue()->getPlainText());
            $this->assertSame("১৫ × ৩ boards\n১২ × ৪ boards\nTotal Boards: 2",$sheet->getCell('K5')->getValue());
            $this->assertSame(2,$sheet->getCell('K6')->getValue());
            $this->assertSame([4,4],$sheet->getPageSetup()->getRowsToRepeatAtTop());
            $this->assertSame('Number of Vacant Posts',$sheet->getCell('P4')->getValue());

            $book->disconnectWorksheets();
        } finally {unlink($tmp);}
    }

    public function test_grouped_pagination_counts_dates_and_retains_all_exams_on_each_page(): void
    {
        for ($i=0;$i<27;$i++) ExamSchedule::create($this->data(['title'=>'Date '.$i,'exam_date'=>today()->addDays($i)->toDateString(),'board_count'=>1]));
        $this->get('/schedules?display=grouped&page=2')->assertOk()
            ->assertViewHas('pagination',fn($p)=>$p->total()===27 && $p->currentPage()===2)
            ->assertViewHas('schedules',fn($rows)=>$rows->count()===2);
        $this->get('/schedules?display=invalid')->assertSessionHasErrors('display');
    }

    public function test_api_supports_structure_manual_totals_and_opted_in_date_groups(): void
    {
        $user = User::factory()->create(['role'=>UserRole::Editor]);
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('Board structure test')->plainTextToken);
        $record = $this->postJson('/api/v1/schedules',$this->data(['board_structure'=>$this->structure(),'vacant_posts'=>'১০']))
            ->assertCreated()->assertJsonPath('data.candidate_count',93)->assertJsonPath('data.board_count',7)
            ->assertJsonPath('data.vacant_posts',10)->assertJsonPath('data.end_time',null)->json('data');
        $this->getJson('/api/v1/schedules?display=grouped')->assertOk()
            ->assertJsonPath('schedules.total',1)->assertJsonPath('schedules.data.0.exam_date','2026-10-09')
            ->assertJsonPath('schedules.data.0.exams.0.id',$record['id']);
        $this->patchJson('/api/v1/schedules/'.$record['id'],$this->data(['version'=>$record['version'],
            'board_structure'=>$this->structure(),'candidate_count'=>100,'board_count'=>8]))
            ->assertOk()->assertJsonPath('data.candidate_count',100)->assertJsonPath('data.board_count',8);
    }

    public function test_pdf_font_runs_preserve_bold_headers_and_render_non_bengali_symbols(): void
    {
        app()->setLocale('bn');
        $schedule = new ExamSchedule(['exam_type'=>'written','start_time'=>'10:00','end_time'=>'13:00']);
        $this->assertSame('১০:০০ AM - ০১:০০ PM',$schedule->timeLabel());
        $this->assertSame('১০:০০ am',\App\Support\Ui::date('10:00','h:i a'));
        $this->assertSame('A ১০:০০ AM',\App\Support\Ui::date('10:00','\\A h:i A'));
        $this->assertSame("১৫ × ৩ টি বোর্ড\n১২ × ৪ টি বোর্ড\nমোট বোর্ড: ৮",(new ExamSchedule([
            'exam_type'=>'viva','board_structure'=>$this->structure(),'board_count'=>8]))->boardsLabel());
        $html = app(\App\Services\Scheduler\SchedulePdf::class)->fontRuns('<th>Status — সময়</th><b>IT Section</b><td>—</td>');
        $this->assertStringContainsString('font-family:dejavusans;font-weight:bold', $html);
        $this->assertStringContainsString('font-family:nikosh;font-weight:bold', $html);
        $this->assertStringContainsString('<span style="font-family:dejavusans">—</span>', $html);
    }

    public function test_nullable_column_migration_preserves_existing_schedule_data(): void
    {
        $s = ExamSchedule::create($this->data(['title'=>'Existing record','board_count'=>7,'candidate_count'=>93,
            'post_grade'=>9,'end_time'=>'12:00']));
        $s->version = 3;
        $s->save();
        $migration = require database_path('migrations/2026_10_09_000008_add_vacant_posts_and_board_structure.php');
        $migration->down();
        $migration->up();
        $existing = $s->fresh();
        $this->assertSame(7,$existing->board_count);
        $this->assertSame(93,$existing->candidate_count);
        $this->assertSame(9,$existing->post_grade);
        $this->assertSame(3,$existing->version);
        $this->assertSame('12:00',$existing->end_time);
        $this->assertNull($existing->vacant_posts);
        $this->assertNull($existing->board_structure);
    }
}
