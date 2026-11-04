<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class AdminNewsletterController extends Controller
{
    public function index()
    {
        return view('admin.newsletters.index', [
            'newsletters' => Newsletter::query()->latest()->get(),
            'subscriberCount' => Newsletter::recipients()->count(),
        ]);
    }

    public function create()
    {
        return view('admin.newsletters.form', [
            'newsletter' => new Newsletter([
                'template' => 'neon',
                'locale' => 'de',
                'from_name' => config('mail.from.name'),
                'from_email' => config('mail.from.address'),
            ]),
            'subscriberCount' => Newsletter::recipients()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $newsletter = new Newsletter;
        $newsletter->created_by_user_id = $request->user()->id;
        $this->persist($request, $newsletter);

        return redirect()->route('admin.newsletters.edit', $newsletter)->with('status', 'Entwurf gespeichert.');
    }

    public function edit(Newsletter $newsletter)
    {
        return view('admin.newsletters.form', [
            'newsletter' => $newsletter,
            'subscriberCount' => Newsletter::recipients()->count(),
        ]);
    }

    public function update(Request $request, Newsletter $newsletter): RedirectResponse
    {
        abort_if($newsletter->status !== 'draft', 403);
        $this->persist($request, $newsletter);

        return redirect()->route('admin.newsletters.edit', $newsletter)->with('status', 'Entwurf gespeichert.');
    }

    public function destroy(Newsletter $newsletter): RedirectResponse
    {
        abort_if($newsletter->status === 'sending', 409);
        $newsletter->deleteImages();
        $newsletter->delete();

        return redirect()->route('admin.newsletters')->with('status', 'Newsletter gelöscht.');
    }

    public function preview(Newsletter $newsletter)
    {
        return response((new NewsletterMail($newsletter))->render());
    }

    public function test(Request $request, Newsletter $newsletter): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['nullable', 'email', 'max:255']]);
        $to = $data['test_email'] ?? $request->user()->email;

        Mail::to($to)->send(new NewsletterMail($newsletter));

        return back()->with('status', 'Testmail an '.$to.' gesendet.');
    }

    public function send(Request $request, Newsletter $newsletter): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']]);

        $claimed = Newsletter::query()->whereKey($newsletter->id)->where('status', 'draft')->update(['status' => 'sending']);
        abort_unless($claimed === 1, 409);

        set_time_limit(0);
        $sent = 0;
        $failed = 0;

        Newsletter::recipients()->chunkById(100, function ($users) use ($newsletter, &$sent, &$failed) {
            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->send(new NewsletterMail($newsletter, $user));
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                    $failed++;
                }
            }
        });

        $newsletter->forceFill(['status' => 'sent', 'sent_at' => now(), 'recipient_count' => $sent])->save();

        return redirect()->route('admin.newsletters')->with('status', "Newsletter an {$sent} Empfänger gesendet".($failed ? ", {$failed} Fehler (siehe Log)." : '.'));
    }

    private function persist(Request $request, Newsletter $newsletter): void
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'from_name' => ['required', 'string', 'max:120'],
            'from_email' => ['required', 'email', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'template' => ['required', Rule::in(array_keys(Newsletter::TEMPLATES))],
            'locale' => ['required', Rule::in(array_keys(Newsletter::LOCALES))],
            'headline' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'button_label' => ['nullable', 'string', 'max:60', 'required_with:button_url'],
            'button_url' => ['nullable', 'url:http,https', 'max:2000', 'required_with:button_label'],
            'header_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
            'body_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
            'remove_header_image' => ['nullable', 'boolean'],
            'remove_body_image' => ['nullable', 'boolean'],
        ]);

        foreach (['header_image', 'body_image'] as $field) {
            $file = $request->file($field);
            $remove = $request->boolean('remove_'.$field);

            if (($file instanceof UploadedFile || $remove) && $newsletter->{$field}) {
                Storage::disk('local')->delete($newsletter->{$field});
                $newsletter->{$field} = null;
            }
            if ($file instanceof UploadedFile) {
                $newsletter->{$field} = $file->store('newsletters', 'local');
            }
        }

        $newsletter->fill(collect($data)->except(['header_image', 'body_image', 'remove_header_image', 'remove_body_image'])->all());
        $newsletter->save();
    }
}
