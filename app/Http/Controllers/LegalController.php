<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function imprint(): View
    {
        return $this->page('imprint');
    }

    public function faq(): View
    {
        return $this->page('faq');
    }

    public function privacy(): View
    {
        return $this->page('privacy');
    }

    public function terms(): View
    {
        return $this->page('terms');
    }

    public function dataDeletion(): View
    {
        return $this->page('data-deletion');
    }

    public function dataDeletionStatus(string $code): View
    {
        $found = DB::table('data_deletion_requests')->where('code', $code)->exists();
        abort_unless($found, 404);

        return view('legal.deletion-status', ['code' => $code]);
    }

    /** Jeder Rechtstext existiert als eigene Fassung je Sprache: resources/views/legal/{de,en,es}/. */
    private function page(string $name): View
    {
        return view('legal.'.app()->getLocale().'.'.$name);
    }
}
