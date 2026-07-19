<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\GetStatsOverviewBusiness;
use app\controllers\GetStatsOverviewController;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class GetStatsOverviewControllerTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = (int) Db::table('users')->insertGetId([
            'email' => 'stats@example.com',
            'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        Db::table('review_events')->where('user_id', $this->userId)->delete();
        Db::table('daily_learning_stats')->where('user_id', $this->userId)->delete();
        Db::table('ai_generation_jobs')->where('user_id', $this->userId)->delete();
        Db::table('memory_cards')->where('user_id', $this->userId)->delete();
        Db::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_empty_overview_uses_user_local_day_and_zero_defaults(): void
    {
        $data = $this->get('2026-07-18 16:30:00');

        self::assertSame('Asia/Shanghai', $data['timezone']);
        self::assertSame('2026-07-19', $data['local_date']);
        self::assertSame(['reviews_completed'=>0,'correct_reviews'=>0,'accuracy'=>0.0,'xp_earned'=>0,'current_correct_streak'=>0,'best_correct_streak'=>0,'daily_goal'=>20,'goal_progress'=>0], $data['today']);
        self::assertSame(['reviews_completed'=>0,'correct_reviews'=>0,'accuracy'=>0.0,'xp_earned'=>0,'level'=>1,'distinct_cards_reviewed'=>0,'best_correct_streak'=>0], $data['lifetime']);
        self::assertSame(0, $data['learning_day_streak']);
        self::assertSame(0, $data['due_count']);
        self::assertSame(0, $data['new_count']);
    }

    public function test_overview_aggregates_today_lifetime_levels_cards_and_queue_counts(): void
    {
        $reviewed = $this->card('reviewed', '2026-07-18 10:00:00', '2026-07-18 15:00:00');
        $this->card('new', null, null);
        $this->stat('2026-07-18', 4, 3, 492, 2, 5);
        $this->stat('2026-07-19', 3, 2, 38, 1, 4);
        $this->event($reviewed, true, 12, '2026-07-19 01:00:00');
        $this->event($reviewed, false, 2, '2026-07-19 02:00:00');

        $data = $this->get('2026-07-19 04:00:00');

        self::assertSame(66.67, $data['today']['accuracy']);
        self::assertSame(38, $data['today']['xp_earned']);
        self::assertSame(3, $data['today']['goal_progress']);
        self::assertSame(7, $data['lifetime']['reviews_completed']);
        self::assertSame(71.43, $data['lifetime']['accuracy']);
        self::assertSame(530, $data['lifetime']['xp_earned']);
        self::assertSame(2, $data['lifetime']['level']);
        self::assertSame(1, $data['lifetime']['distinct_cards_reviewed']);
        self::assertSame(5, $data['lifetime']['best_correct_streak']);
        self::assertSame(2, $data['learning_day_streak']);
        self::assertSame(1, $data['due_count']);
        self::assertSame(1, $data['new_count']);
    }

    public function test_learning_streak_may_end_yesterday_but_not_earlier(): void
    {
        $this->stat('2026-07-16', 1, 1, 10, 1, 1);
        $this->stat('2026-07-17', 1, 1, 10, 1, 1);
        $this->stat('2026-07-18', 1, 1, 10, 1, 1);
        self::assertSame(3, $this->get('2026-07-19 04:00:00')['learning_day_streak']);

        Db::table('daily_learning_stats')->where('user_id', $this->userId)->where('stat_date', '2026-07-18')->delete();
        self::assertSame(0, $this->get('2026-07-19 04:00:00')['learning_day_streak']);
    }

    public function test_authenticated_stats_route_and_controller_are_wired(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');
        self::assertMatchesRegularExpression("~Route::get\('/api/stats/overview'.+?->middleware\(\[Authenticate::class\]\);~s", $routes);

        $request = new Request("GET /api/stats/overview HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setAuthenticatedUserId($this->userId);
        $response = (new GetStatsOverviewController(new GetStatsOverviewBusiness()))($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue(json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['success']);
    }

    private function get(string $utc): array
    {
        return (new GetStatsOverviewBusiness())->get($this->userId, new DateTimeImmutable($utc, new DateTimeZone('UTC')))->toResponseArray()['data'];
    }

    private function stat(string $date, int $reviews, int $correct, int $xp, int $current, int $best): void
    {
        Db::table('daily_learning_stats')->insert(['user_id'=>$this->userId,'stat_date'=>$date,'reviews_completed'=>$reviews,'correct_reviews'=>$correct,'xp_earned'=>$xp,'current_correct_streak'=>$current,'best_correct_streak'=>$best,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
    }

    private function card(string $word, ?string $firstReviewedAt, ?string $nextReviewAt): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('memory_cards')->insertGetId(['user_id'=>$this->userId,'source_text'=>$word,'normalized_text'=>$word,'content_type'=>'word','memory_style'=>'auto','card_payload'=>json_encode(['word'=>$word]),'first_reviewed_at'=>$firstReviewedAt,'next_review_at'=>$nextReviewAt,'created_at'=>$now,'updated_at'=>$now]);
        Db::table('ai_generation_jobs')->insert(['user_id'=>$this->userId,'memory_card_id'=>$id,'idempotency_key'=>'stats-'.$id,'request_hash'=>hash('sha256', $word),'request_payload'=>json_encode(['text'=>$word]),'status'=>'completed','attempts'=>1,'created_at'=>$now,'updated_at'=>$now]);
        return $id;
    }

    private function event(int $cardId, bool $correct, int $xp, string $reviewedAt): void
    {
        Db::table('review_events')->insert(['user_id'=>$this->userId,'memory_card_id'=>$cardId,'idempotency_key'=>'event-'.$reviewedAt,'request_hash'=>hash('sha256', $reviewedAt),'mode'=>'zh_to_en','answer_text'=>'x','normalized_answer'=>'x','is_correct'=>$correct,'difficulty'=>'good','rating'=>$correct ? 'good' : 'again','effective_rating'=>$correct ? 'good' : 'again','previous_stage'=>0,'next_stage'=>$correct ? 1 : 0,'previous_due_at'=>null,'next_due_at'=>$reviewedAt,'response_ms'=>1,'xp_awarded'=>$xp,'response_payload'=>json_encode([]),'reviewed_at'=>$reviewedAt,'created_at'=>$reviewedAt]);
    }
}
