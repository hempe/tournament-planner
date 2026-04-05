<?php

declare(strict_types=1);

namespace TP\Controllers;

use TP\Core\Request;
use TP\Core\Response;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Post;
use TP\Core\Attributes\Middleware;
use TP\Middleware\AuthMiddleware;
use TP\Middleware\AdminMiddleware;
use TP\Models\DB;

#[RoutePrefix('/admin')]
#[Middleware(AuthMiddleware::class)]
#[Middleware(AdminMiddleware::class)]
final class AdminController
{
    #[Get('/migrate-names')]
    public function migrateNamesForm(Request $request): Response
    {
        $pending = DB::$users->countWithoutNames();
        $html = <<<HTML
        <html><head><meta charset="utf-8"><title>Migrate Names</title>
        <style>body{font-family:sans-serif;max-width:600px;margin:40px auto;padding:0 20px}
        button{padding:10px 20px;background:#b6334d;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:1rem}
        button:hover{background:#8f2238}</style></head>
        <body>
        <h2>Migrate Names</h2>
        <p><strong>$pending</strong> user(s) still need first/last name seeded from username.</p>
        <form method="POST" action="/admin/migrate-names">
            <button type="submit">Run migration</button>
        </form>
        <p><a href="/">← Back</a></p>
        </body></html>
        HTML;

        return Response::ok($html);
    }

    #[Post('/migrate-names')]
    public function migrateNames(Request $request): Response
    {
        $results = DB::$users->seedNamesFromUsernames();

        $rows = '';
        foreach ($results as $r) {
            $status = htmlspecialchars($r['status']);
            $id     = (int) $r['id'];
            $raw    = htmlspecialchars($r['username']);
            $first  = htmlspecialchars($r['first']);
            $last   = htmlspecialchars($r['last']);
            $rows .= "<tr><td>$id</td><td>$raw</td><td>$first</td><td>$last</td><td>$status</td></tr>\n";
        }

        $updated = count(array_filter($results, fn($r) => $r['status'] === 'updated'));
        $skipped = count($results) - $updated;

        $html = <<<HTML
        <html><head><meta charset="utf-8"><title>Migrate Names — Done</title>
        <style>body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:0 20px}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:6px 10px;border-bottom:1px solid #ddd}
        th{background:#f0f0f0}.ok{color:#4a7c1f}.skip{color:#888}</style></head>
        <body>
        <h2>Migration complete</h2>
        <p>Updated: <strong>$updated</strong> &nbsp; Skipped: <strong>$skipped</strong></p>
        <table>
        <tr><th>ID</th><th>Username</th><th>First name</th><th>Last name</th><th>Status</th></tr>
        $rows
        </table>
        <p><a href="/">← Back</a></p>
        </body></html>
        HTML;

        return Response::ok($html);
    }
}
