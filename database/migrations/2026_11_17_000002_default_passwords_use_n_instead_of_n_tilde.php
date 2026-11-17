<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Default passwords now turn ñ into n (Peña → Pena105680). Students with an ñ in their name who
     * are still on the old default get the new one; anyone who set their own password is left alone.
     */
    public function up(): void
    {
        $this->swap(fn ($user) => $this->oldDefault($user), fn ($user) => User::defaultPassword($user->name, $user->username));
    }

    public function down(): void
    {
        $this->swap(fn ($user) => User::defaultPassword($user->name, $user->username), fn ($user) => $this->oldDefault($user));
    }

    private function swap(callable $from, callable $to): void
    {
        // Filtered in PHP: MySQL's collation treats ñ and n as equal, so LIKE '%ñ%' would match every n.
        DB::table('users')->whereNull('password_changed_at')->select('id', 'name', 'username', 'password')
            ->orderBy('id')->get()
            ->filter(fn ($user) => str_contains(Str::lower($user->name), 'ñ'))
            ->each(function ($user) use ($from, $to) {
                if (Hash::check($from($user), $user->password)) {
                    DB::table('users')->where('id', $user->id)->update(['password' => Hash::make($to($user))]);
                }
            });
    }

    /** The default password before this change, ñ kept. */
    private function oldDefault(object $user): string
    {
        $lastName = str_contains($user->name, ',') ? Str::before($user->name, ',') : Str::afterLast(trim($user->name), ' ');
        $number = preg_match('/(\d+)$/', $user->username, $m) ? $m[1] : substr($user->username, -6);

        return Str::ucfirst(Str::lower(preg_replace('/\s+/', '', $lastName))) . $number;
    }
};
