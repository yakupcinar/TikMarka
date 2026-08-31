<?php

namespace App\Tenancy\Commands;

use App\Domain\Identity\EmailNormalizer;
use App\Platform\DomainUnavailableException;
use App\Platform\TenantProvisioning;
use App\Platform\WeeklyLimitReachedException;
use Illuminate\Console\Command;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create
                            {ad : Markanın adı (ör. "A Markası")}
                            {alan-adi : Markanın alan adı (ör. marka-a.localhost)}
                            {--sahip-eposta= : Sahip kullanıcının e-postası (varsayılan: sahip@<alan-adi>)}
                            {--sahip-parola=123 : Sahip kullanıcının parolası}';

    protected $description = 'Yeni marka açar: şema oluşturur, tablolarını kurar, alan adını bağlar.';

    public function handle(TenantProvisioning $kurulum): int
    {
        $ad = trim((string) $this->argument('ad'));
        $alanAdi = strtolower(trim((string) $this->argument('alan-adi')));

        if ($ad === '' || $alanAdi === '') {
            $this->error('Marka adı ve alan adı boş olamaz.');

            return self::FAILURE;
        }

        $sahipEposta = (string) EmailNormalizer::normallestir(
            (string) ($this->option('sahip-eposta') ?: "sahip@{$alanAdi}")
        );
        $sahipParola = (string) $this->option('sahip-parola');

        $this->info("Marka oluşturuluyor: {$ad}");

        try {
            $tenant = $kurulum->ac($ad, $alanAdi, $sahipEposta, $sahipParola);
        } catch (DomainUnavailableException|WeeklyLimitReachedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Marka oluşturulamadı, yarım kalan kayıt temizlendi:');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("  kimlik   : {$tenant->id}");
        $this->line("  sahip    : {$sahipEposta}  (parola: {$sahipParola})");
        $this->line('  şema     : '.$tenant->database()->getName());
        $this->line("  adres    : https://{$alanAdi}");
        $this->line('  durum    : '.$tenant->status?->value.'  (deneme bitişi: '.$tenant->trial_ends_at?->toDateString().')');
        $this->newLine();
        $this->warn('⚠ Sahip parolası komut satırında görünüyor — ilk girişte değiştirilmeli.');
        $this->warn('Mağaza KAPALI açıldı. Panelden şirket bilgilerini doldurup');
        $this->warn('üç yasal metni yayınlayınca /panel/store/publish çalışacak.');

        $this->warn("Geliştirme: {$alanAdi} adresini docker/caddy/Caddyfile'a ekleyip");
        $this->warn('"docker compose restart caddy" demeden HTTPS açılmaz (Faz 3: on-demand TLS).');

        return self::SUCCESS;
    }
}
