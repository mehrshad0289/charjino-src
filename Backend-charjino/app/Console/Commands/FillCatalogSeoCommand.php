<?php

namespace App\Console\Commands;

use App\Models\CatalogGroup;
use Illuminate\Console\Command;

class FillCatalogSeoCommand extends Command
{
    protected $signature = 'charjino:fill-catalog-seo {--force : Replace filled SEO fields}';

    protected $description = 'عنوان و توضیح سئوی دسته‌ها را اگر خالی باشد با الگوی پیش‌فرض پر می‌کند';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $copy = [
            'game-topup' => [
                'title' => 'خرید شارژ بازی با تومان ارزان | تحویل فوری',
                'description' => 'شارژ بازی با تومان و تحویل فوری: یوسی پابجی، سی‌پی کالاف و الماس فری فایر با Player ID. پشتیبانی شارژینو پاسخگوست؛ پرداخت ریالی بدون رمز اکانت.',
            ],
            'gift-card' => [
                'title' => 'خرید گیفت کارت با تومان ارزان | تحویل فوری',
                'description' => 'خرید گیفت کارت با تومان و تحویل فوری؛ پلی‌استیشن، استیم و گوگل پلی. پشتیبانی شارژینو پاسخگوست؛ پرداخت ریالی بدون رمز اکانت فروشگاه.',
            ],
            'game-keys' => [
                'title' => 'خرید کلید بازی استیم و کنسول | تحویل فوری',
                'description' => 'خرید کلید بازی استیم و کنسول را با تومان و تحویل فوری از شارژینو بخرید. پشتیبانی پاسخگوست؛ پرداخت ریالی بدون رمز اکانت لانچر.',
            ],
            'telegram' => [
                'title' => 'خرید پرمیوم و استارز تلگرام | تحویل فوری',
                'description' => 'خرید پرمیوم و استارز تلگرام با تومان و تحویل فوری. پشتیبانی شارژینو پاسخگوست؛ فقط یوزرنیم عمومی لازم است و پرداخت ریالی است.',
            ],
            'steam-wallet' => [
                'title' => 'شارژ کیف پول استیم با تومان | تحویل فوری',
                'description' => 'شارژ کیف پول استیم را با تومان و تحویل فوری انجام دهید. پشتیبانی شارژینو پاسخگوست؛ پرداخت ریالی بدون رمز ورود و Steam Guard.',
            ],
            'steam-gifts' => [
                'title' => 'خرید گیفت استیم با تومان ارزان | تحویل فوری',
                'description' => 'خرید گیفت استیم با تومان و تحویل فوری به کتابخانه استیم. پشتیبانی شارژینو پاسخگوست؛ پرداخت ریالی بدون رمز اکانت گیرنده. سفارش فقط از خود سایت ثبت می‌شود.',
            ],
        ];

        $updated = 0;
        foreach ($copy as $slug => $seo) {
            $group = CatalogGroup::query()->where('slug', $slug)->first();
            if (! $group) {
                $this->warn('missing '.$slug);
                continue;
            }
            if (! $force && filled($group->seo_title) && filled($group->seo_description)) {
                continue;
            }
            $group->seo_title = $seo['title'];
            $group->seo_description = $seo['description'];
            $group->save();
            $updated++;
            $this->line($slug.' '.$seo['title']);
        }
        $this->info('updated='.$updated);

        return self::SUCCESS;
    }
}
