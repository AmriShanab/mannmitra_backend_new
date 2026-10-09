@extends('layouts.legal')

@section('title', 'Privacy Policy')
@section('description', 'How MannMitra collects, uses and protects your information.')
@section('eyebrow', 'Your privacy matters')
@section('heading', 'Privacy Policy')
@section('subtitle', 'A clear, honest explanation of what we collect, why we collect it, and the control you have over your data.')

@section('cta_title', 'Questions about your data?')
@section('cta_text', 'Read how using MannMitra works in our Terms & Conditions.')
@section('cta_url', route('terms.and.conditions'))
@section('cta_button', 'View Terms & Conditions')

@section('content')

<section class="sec">
    <h2>Who we are</h2>
    <p>MannMitra ("we", "us", "our") is operated by <mark class="ph">[COMPANY / LEGAL NAME]</mark>, <mark class="ph">[ADDRESS]</mark>, India.</p>
    <ul>
        <li>Privacy questions: <mark class="ph">[EMAIL]</mark></li>
        <li>Grievance Officer: <mark class="ph">[NAME]</mark>, <mark class="ph">[EMAIL]</mark></li>
    </ul>
</section>

<section class="sec">
    <h2>What MannMitra is</h2>
    <p>MannMitra is an emotional-wellbeing app. It offers an AI companion, mood tracking, journaling, CBT-style exercises, and paid sessions with human listeners and mental-health professionals.</p>
    <div class="callout danger">
        <div class="ic">🚨</div>
        <div><strong>MannMitra is not an emergency service</strong> and does not replace professional medical care.</div>
    </div>
</section>

<section class="sec">
    <h2>Information we collect</h2>

    <h3>Information you give us</h3>
    <div class="cards">
        <div class="card"><div class="emoji">💬</div><h4>Chats</h4><p>Messages and voice recordings shared with the AI companion or listeners.</p></div>
        <div class="card"><div class="emoji">📓</div><h4>Mood &amp; journals</h4><p>The text you write and your mood ratings.</p></div>
        <div class="card"><div class="emoji">🧠</div><h4>Activity responses</h4><p>Your answers to CBT exercises.</p></div>
        <div class="card"><div class="emoji">👤</div><h4>Optional account details</h4><p>Name, email and password, only if you choose to register.</p></div>
        <div class="card"><div class="emoji">📅</div><h4>Appointments</h4><p>Time, mode (audio or video) and notes for appointments you book.</p></div>
        <div class="card"><div class="emoji">🌐</div><h4>Preferences</h4><p>Your language preference.</p></div>
    </div>

    <h3>Information created automatically</h3>
    <ul>
        <li><strong>Anonymous ID:</strong> a random identifier assigned when you start a session, so you can use the app without giving your name.</li>
        <li><strong>Device and usage data:</strong> push-notification token, last-active time, session type and app analytics (via Firebase Analytics).</li>
        <li><strong>Crisis flags:</strong> if your messages contain words suggesting self-harm, we record an alert (the trigger phrase, severity and status) linked to your session.</li>
        <li><strong>Technical data:</strong> IP address, device type, and connectivity information needed for calls.</li>
    </ul>

    <h3>Payment information</h3>
    <p>Payments are processed by <strong>Razorpay</strong>. We do not store your card, UPI or bank details. We keep the order ID, payment ID, amount, plan and status.</p>

    <h3>WhatsApp</h3>
    <p>If you message our WhatsApp number, we collect your phone number and the content of your text or voice messages.</p>

    <h3>Permissions we request</h3>
    <ul>
        <li><strong>Microphone:</strong> voice messages and audio or video consultations.</li>
        <li><strong>Camera:</strong> video consultations.</li>
        <li><strong>Notifications:</strong> reminders, mood check-ins and appointment alerts.</li>
        <li><strong>Bluetooth and network state:</strong> call audio routing and connection quality.</li>
    </ul>
    <p>You can turn these off in your device settings. Some features will not work without them.</p>
</section>

<section class="sec">
    <h2>How we use your information</h2>
    <ul>
        <li>To provide the AI companion, journaling, mood, CBT and call features.</li>
        <li>To generate AI replies, transcriptions, spoken replies and journal reflections.</li>
        <li>To detect possible crisis situations and show you helpline information.</li>
        <li>To match you with listeners or professionals and to run appointments, including reminders about 30 minutes before.</li>
        <li>To process payments, manage subscriptions and prevent fraud.</li>
        <li>To send notifications, improve and secure the app, fix bugs and meet legal obligations.</li>
    </ul>
    <div class="callout">
        <div class="ic">🔒</div>
        <div>We do <strong>not</strong> sell your personal data, and we do not use your private conversations for advertising.</div>
    </div>
</section>

<section class="sec">
    <h2>AI processing</h2>
    <p>Your messages, voice recordings and journal content are sent to <strong>OpenAI</strong> to generate replies and transcriptions. OpenAI processes this data under its own terms and privacy policy.</p>
    <p>AI replies can be inaccurate and are not medical advice. Please don't share information you are not comfortable sending to an AI provider.</p>
</section>

<section class="sec">
    <h2>Who we share information with</h2>
    <p>We share information only as needed, with:</p>
    <div class="chips">
        <span class="chip">OpenAI <small>· AI chat, speech</small></span>
        <span class="chip">Google Firebase <small>· notifications, analytics</small></span>
        <span class="chip">Razorpay <small>· payments</small></span>
        <span class="chip">Whapi <small>· WhatsApp bot</small></span>
        <span class="chip">Hosting providers <small>· servers, call relay</small></span>
    </div>
    <ul>
        <li><strong>Listeners and professionals you connect with:</strong> they see the messages and details of your session or appointment.</li>
        <li><strong>Authorities:</strong> where required by law, or to protect someone's life or safety.</li>
    </ul>
    <p>Audio and video calls are peer-to-peer where possible, and may pass through our relay (TURN) server. <strong>We do not record calls</strong> <mark class="ph">[CONFIRM THIS]</mark>.</p>
</section>

<section class="sec">
    <h2>Anonymity</h2>
    <p>You can use MannMitra without an account. Your activity is tied to a random anonymous ID, not your name. If you register, link a WhatsApp number or pay, you become more identifiable. Anonymity is not guaranteed if you share identifying details yourself.</p>
</section>

<section class="sec">
    <h2>Crisis and safety exception</h2>
    <p>Your conversations are normally confidential. If we believe there is a serious risk to your life or someone else's, we may share necessary information with emergency services, or your emergency contacts if you have provided them, to the extent permitted by law.</p>
</section>

<section class="sec">
    <h2>Data retention</h2>
    <p>We keep your data while your account or anonymous profile is active and as long as needed to provide the service. After that, we keep it only as required for legal, tax, fraud-prevention or dispute purposes. Payment records are kept as required by Indian law.</p>
    <p>Typical retention: <mark class="ph">[SPECIFY, e.g., chats and journals deleted [X] days after deletion request or inactivity]</mark>.</p>
</section>

<section class="sec">
    <h2>Your rights and choices</h2>
    <p>Under the Digital Personal Data Protection Act, 2023 and applicable law, you may:</p>
    <ul>
        <li>access the personal data we hold about you</li>
        <li>correct inaccurate data</li>
        <li>delete your data or account</li>
        <li>withdraw consent</li>
        <li>nominate someone to exercise rights on your behalf</li>
        <li>raise a grievance</li>
    </ul>
    <p>To exercise these rights, email <mark class="ph">[EMAIL]</mark>. We will respond within <mark class="ph">[30]</mark> days. You can also delete your whole account inside the app (Profile &rarr; Delete my account) or follow the steps on our <a href="{{ route('account.deletion') }}">account deletion page</a>.</p>
</section>

<section class="sec">
    <h2>Security</h2>
    <p>We use measures such as encrypted connections, hashed passwords, access tokens and secure on-device storage. No system is 100% secure, so we cannot guarantee absolute security. If you suspect unauthorized access, contact us immediately.</p>
</section>

<section class="sec">
    <h2>Children</h2>
    <p>MannMitra is intended for users aged <strong>18 and above</strong> <mark class="ph">[CONFIRM; if you allow 13–17, add a parental-consent clause as the DPDP Act requires]</mark>. We do not knowingly collect data from children. If you believe a child has used the app, contact us and we will delete the data.</p>
</section>

<section class="sec">
    <h2>International transfers</h2>
    <p>Some providers (e.g., OpenAI, Google) may process data outside India. By using MannMitra you consent to this transfer, subject to applicable law.</p>
</section>

<section class="sec">
    <h2>Changes to this policy</h2>
    <p>We may update this policy. We will notify you in the app and update the date above. Continued use means you accept the changes.</p>
</section>

<section class="sec">
    <h2>Contact us</h2>
    <ul>
        <li><mark class="ph">[COMPANY NAME]</mark></li>
        <li>Email: <mark class="ph">[EMAIL]</mark></li>
        <li>Phone: <mark class="ph">[PHONE]</mark></li>
        <li>Address: <mark class="ph">[ADDRESS]</mark></li>
        <li>Grievance Officer: <mark class="ph">[NAME, EMAIL]</mark></li>
    </ul>
</section>

@endsection
