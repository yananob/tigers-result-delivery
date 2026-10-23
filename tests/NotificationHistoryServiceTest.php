<?php

namespace Tests;

use App\NotificationHistoryService;
use Carbon\Carbon;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient as GoogleFirestoreClient;
use PHPUnit\Framework\TestCase;

class NotificationHistoryServiceTest extends TestCase
{
    public function testGetRecentHistoryWithMockFirestore(): void
    {
        $mockSnapshot = $this->createMock(DocumentSnapshot::class);
        $mockSnapshot->method('exists')->willReturnCallback(function () {
            return true;
        });
        $mockSnapshot->method('data')->willReturn([
            'is_notified' => true,
            'timestamp' => '2026-10-23T18:00:00+09:00',
        ]);

        $mockDocRef = $this->createMock(DocumentReference::class);
        $mockDocRef->method('snapshot')->willReturn($mockSnapshot);

        $mockCollectionRef = $this->createMock(CollectionReference::class);
        $mockCollectionRef->method('document')->willReturn($mockDocRef);

        $mockFirestore = $this->createMock(GoogleFirestoreClient::class);
        $mockFirestore->method('collection')->willReturn($mockCollectionRef);

        $service = new NotificationHistoryService($mockFirestore);
        $baseDate = Carbon::create(2026, 10, 23, 12, 0, 0, 'Asia/Tokyo');

        $history = $service->getRecentHistory(7, $baseDate);

        $this->assertCount(7, $history);

        // Verify first item is base date (2026-10-23)
        $this->assertEquals('2026-10-23', $history[0]['date']);
        $this->assertEquals('2026-10-23 (金)', $history[0]['formatted_date']);
        $this->assertTrue($history[0]['is_notified']);
        $this->assertEquals('通知済み', $history[0]['status_label']);
        $this->assertEquals('2026-10-23 18:00:00', $history[0]['timestamp']);

        // Verify last item is 6 days ago (2026-10-17)
        $this->assertEquals('2026-10-17', $history[6]['date']);
        $this->assertEquals('2026-10-17 (土)', $history[6]['formatted_date']);
    }
}
