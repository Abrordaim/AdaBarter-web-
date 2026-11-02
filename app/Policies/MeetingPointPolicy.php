<?php

namespace App\Policies;

use App\Models\MeetingPoint;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MeetingPointPolicy
{
    /**
     * Menentukan apakah pengguna boleh melihat detail titik temu pada penawaran ini.
     */
    public function view(User $user, Offer $offer): Response
    {
        if ($user->isAdmin() || $offer->offerer_user_id === $user->id || $offer->target_user_id === $user->id) {
            return Response::allow();
        }

        return Response::deny('Anda bukan peserta dalam transaksi barter ini.');
    }

    /**
     * Menentukan apakah pengguna boleh mengusulkan titik temu baru.
     */
    public function propose(User $user, Offer $offer): Response
    {
        if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id && !$user->isAdmin()) {
            return Response::deny('Hanya peserta transaksi yang dapat mengusulkan titik temu.');
        }

        if ($offer->status !== 'matched') {
            return Response::deny('Titik temu hanya dapat diusulkan setelah penawaran barter disepakati bersama (Matched).');
        }

        return Response::allow();
    }

    /**
     * Menentukan apakah pengguna boleh menyetujui usulan titik temu.
     */
    public function agree(User $user, MeetingPoint $meetingPoint): Response
    {
        $offer = $meetingPoint->offer;

        if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id && !$user->isAdmin()) {
            return Response::deny('Hanya peserta transaksi yang dapat menyetujui titik temu.');
        }

        if ($offer->status !== 'matched') {
            return Response::deny('Transaksi barter sudah tidak berstatus matched.');
        }

        if ($meetingPoint->status !== 'proposed') {
            return Response::deny('Usulan titik temu ini sudah tidak aktif atau sudah diproses sebelumnya.');
        }

        // Pengusul TIDAK BISA menyetujui usulannya sendiri
        if ($meetingPoint->proposed_by === $user->id && !$user->isAdmin()) {
            return Response::deny('Anda tidak dapat menyetujui usulan titik temu yang Anda ajukan sendiri.');
        }

        return Response::allow();
    }
}
