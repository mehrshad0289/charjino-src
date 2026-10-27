<?php

use Illuminate\Support\Facades\Schedule;

$fazerLog = storage_path('logs/fazercards-sync.log');

Schedule::command('charjino:refresh-fx')->dailyAt('09:10');
Schedule::command('charjino:sync-search-tags')->dailyAt('09:40');
Schedule::command('charjino:sync-fazercards')->dailyAt('09:20')->withoutOverlapping()->onOneServer()->appendOutputTo($fazerLog);
Schedule::command('charjino:sync-fazercards-light')->everyFifteenMinutes()->withoutOverlapping()->onOneServer()->appendOutputTo($fazerLog);
Schedule::command('charjino:expire-url-lifecycle')->dailyAt('03:10')->withoutOverlapping()->onOneServer();
Schedule::command('charjino:sync-fazercards steam-gifts --mode=visible')->everyThreeHours()->withoutOverlapping()->onOneServer()->appendOutputTo($fazerLog);
Schedule::command('charjino:sync-fazercards steam-gifts --mode=weekly')->dailyAt('01:00')->withoutOverlapping(450)->onOneServer()->appendOutputTo($fazerLog);
Schedule::command('charjino:resolve-steam-covers')->dailyAt('11:00')->withoutOverlapping(720);
Schedule::command('charjino:refresh-steam-gift-index')->dailyAt('11:20')->withoutOverlapping(120)->onOneServer();
Schedule::command('charjino:poll-fulfillment')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('charjino:reconcile-fazer')->everyTwoMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('charjino:fazer-balance-watch')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('charjino:fazer-webhook-health')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('smart-search:status')->dailyAt('03:40')->withoutOverlapping(30)->onOneServer();
Schedule::command('charjino:publish-scheduled-posts')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('charjino:prune-blog-images')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('products:find-duplicates --alert')->dailyAt('04:20')->withoutOverlapping()->onOneServer();
