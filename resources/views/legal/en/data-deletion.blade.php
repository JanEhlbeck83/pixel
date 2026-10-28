@extends('legal.layout')

@section('title', 'Delete data')

@section('content')
    <p>
        You can have your account and the personal data associated with it deleted at any time.
        This also applies if you signed in via <strong>Facebook</strong> or Google.
    </p>

    <h2>Option 1: Delete it yourself (immediate)</h2>
    <ol>
        <li><a href="{{ route('login') }}">Log in</a> (Facebook is also possible).</li>
        <li>Open your <a href="{{ route('profile.edit') }}">profile</a>.</li>
        <li>Choose “Delete account” and confirm.</li>
    </ol>
    <p>
        Note: if you signed in only with Facebook or Google, you may not know a password. In that case use
        <a href="{{ route('password.request') }}">Forgot password</a> with your account email to set one,
        or choose option 2.
    </p>

    <h2>Option 2: Request by email</h2>
    <p>
        Write to <x-legal-value key="email" /> with the subject “Data deletion”. Include the email address of
        your account (for Facebook login, the email address of your Facebook account). We will process the
        request within 30 days and confirm the deletion to you.
    </p>

    <h2>What is deleted?</h2>
    <ul>
        <li>Account with name, email address, password hash and the Facebook or Google ID</li>
        <li>The link between your pixels and activity and your account (the pixels remain anonymously on the wall)</li>
        <li>Login sessions and credentials</li>
    </ul>
    <p>
        Orders are retained as accounting records without a link to an account, insofar as we are required to
        keep them for tax and commercial-law reasons.
    </p>

    <h2>Remove the link on Facebook</h2>
    <ol>
        <li>On Facebook, open <em>Settings &amp; privacy → Settings</em>.</li>
        <li>Choose <em>Apps and websites</em> (or “Your activity off Meta technologies”).</li>
        <li>Select “Pixel Chaos” and click <em>Remove</em>.</li>
    </ol>
    <p>
        Pixel Chaos can then no longer receive any further data from Facebook. You can remove data already
        stored as described above. Further information: <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.
    </p>
@endsection

