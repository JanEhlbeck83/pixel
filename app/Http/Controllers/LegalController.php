<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    public function imprint(): View
    {
        return $this->page('imprint');
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

    /** Jeder Rechtstext existiert als eigene Fassung je Sprache: resources/views/legal/{de,en,es}/. */
    private function page(string $name): View
    {
        return view('legal.'.app()->getLocale().'.'.$name);
    }
}
