<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>処理履歴 - 阪神タイガース試合結果通知</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #333;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            @if(!empty($isTestOrDev))
            padding-top: 40px;
            @endif
        }
        .env-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #fff3cd;
            color: #856404;
            border-bottom: 1px solid #ffeeba;
            padding: 6px 16px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            z-index: 9999;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .header-bg {
            background: linear-gradient(135deg, #1a1a1a 0%, #333333 100%);
            color: #ffe200; /* Tigers Yellow */
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 24px;
        }
        .table-custom th {
            background-color: #f1f3f5;
            color: #495057;
            font-weight: 600;
        }
        .badge-notified {
            background-color: #198754;
            font-size: 0.85rem;
            padding: 0.4em 0.75em;
        }
        .badge-unnotified {
            background-color: #6c757d;
            font-size: 0.85rem;
            padding: 0.4em 0.75em;
        }
    </style>
</head>
<body>
    @if(!empty($isTestOrDev))
    <div class="env-overlay">
        [テスト/開発環境: {{ strtoupper($env) }}] ベースパス: <code>{{ $basePath }}</code> | リクエストパス: <code>{{ $requestPath }}</code>
    </div>
    @endif

    <div class="container my-5" style="max-width: 800px;">
        <div class="card card-custom">
            <div class="header-bg">
                <h1 class="h3 mb-1 font-weight-bold">🐯 阪神タイガース 試合結果通知</h1>
                <p class="mb-0 text-white-50">直近1週間の処理履歴</p>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover table-custom align-middle">
                        <thead>
                            <tr>
                                <th scope="col">日付</th>
                                <th scope="col" class="text-center">ステータス</th>
                                <th scope="col">通知処理日時</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(!empty($history))
                                @foreach($history as $item)
                                <tr>
                                    <td class="fw-bold">{{ $item['formatted_date'] }}</td>
                                    <td class="text-center">
                                        @if($item['is_notified'])
                                            <span class="badge rounded-pill badge-notified">通知済み</span>
                                        @else
                                            <span class="badge rounded-pill badge-unnotified">未通知</span>
                                        @endif
                                    </td>
                                    <td class="text-secondary font-monospace">{{ $item['timestamp'] }}</td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">処理履歴はありません</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light text-muted text-center py-3" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                <small>Tigers Result Delivery History Monitor</small>
            </div>
        </div>
    </div>
</body>
</html>
