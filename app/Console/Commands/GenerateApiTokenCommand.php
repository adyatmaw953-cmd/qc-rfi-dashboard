<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('api:token {nama : Nama pengguna integrasi}')]
#[Description('Buat token API dengan ability mdr:read untuk integrasi')]
class GenerateApiTokenCommand extends Command
{
    public function handle(): int
    {
        $nama = trim((string) $this->argument('nama'));

        if ($nama === '') {
            $this->error('Nama tidak boleh kosong.');

            return self::FAILURE;
        }

        $email = Str::slug($nama).'-api@mudalaya.internal';

        $user = User::query()
            ->where('name', $nama)
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => $nama,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'role' => 'ppic',
                'is_active' => true,
            ]);
        }

        $plainTextToken = $user->createToken('mdr-read', ['mdr:read'])->plainTextToken;

        $this->info("Token API untuk integrasi '{$user->name}':");
        $this->line($plainTextToken);

        return self::SUCCESS;
    }
}
