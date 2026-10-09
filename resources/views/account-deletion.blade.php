@extends('layouts.legal')

@section('title', 'Delete Your Account')
@section('description', 'How to delete your MannMitra account and personal data.')
@section('eyebrow', 'Your data, your choice')
@section('heading', 'Delete Your Account')
@section('subtitle', 'You can permanently delete your MannMitra account and personal data at any time. Here is how, and exactly what happens.')

@section('cta_title', 'Want to know how we handle your data?')
@section('cta_text', 'Our Privacy Policy explains what we collect and your rights.')
@section('cta_url', route('privacy.policy'))
@section('cta_button', 'View Privacy Policy')

@section('content')

<section class="sec">
    <h2>Delete inside the app (fastest)</h2>
    <ul>
        <li>Open the <strong>MannMitra</strong> app and tap the <strong>Profile</strong> tab.</li>
        <li>Scroll down and tap <strong>Delete my account</strong>.</li>
        <li>Read the message and confirm. Your account is deleted immediately and the app resets.</li>
    </ul>
    <div class="callout">
        <div class="ic">⚠️</div>
        <div>Deletion is <strong>permanent</strong> and cannot be undone. Your data cannot be restored afterwards.</div>
    </div>
</section>

<section class="sec">
    <h2>What is deleted</h2>
    <ul>
        <li>Your AI chat conversations and any safety alerts linked to them</li>
        <li>Your journal entries and AI reflections</li>
        <li>Your mood history</li>
        <li>Your listener chat messages</li>
        <li>Your notifications, your device push token and every sign-in token on all devices</li>
        <li>Your profile details (name, email and your anonymous ID are removed or replaced)</li>
        <li>Notes on your appointments. Upcoming appointments and open listener tickets are cancelled or closed.</li>
    </ul>
</section>

<section class="sec">
    <h2>What we keep, and why</h2>
    <ul>
        <li><strong>Payment and subscription records</strong> (amount, date, payment ID). Indian tax and accounting law requires us to keep these. After deletion they are no longer linked to any name, email or contact detail.</li>
        <li>Standard security logs may be kept for a short period and are deleted automatically.</li>
    </ul>
    <p>If you had a paid upcoming appointment that was cancelled by deleting your account, contact us for a refund.</p>
</section>

<section class="sec">
    <h2>Cannot open the app?</h2>
    <p>If you uninstalled the app or cannot sign in, email us from the address you registered with, or tell us your anonymous ID, and ask us to delete your account. We will confirm and complete the deletion within <mark class="ph">[30]</mark> days.</p>
    <ul>
        <li>Email: <mark class="ph">[EMAIL]</mark></li>
        <li>Subject line: <strong>Delete my MannMitra account</strong></li>
    </ul>
    <p>If you also chatted with us on WhatsApp, include the phone number you used so we can delete that conversation too.</p>
</section>

@endsection
