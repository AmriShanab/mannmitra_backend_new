@extends('layouts.legal')

@section('title', 'Terms & Conditions')
@section('description', 'The terms that govern your use of MannMitra.')
@section('eyebrow', 'Using MannMitra')
@section('heading', 'Terms & Conditions')
@section('subtitle', 'The ground rules for using MannMitra: what we offer, what we expect, and where our responsibilities begin and end.')

@section('cta_title', 'Want to know how we handle your data?')
@section('cta_text', 'Our Privacy Policy explains what we collect and your rights.')
@section('cta_url', route('privacy.policy'))
@section('cta_button', 'View Privacy Policy')

@section('content')

<section class="sec">
    <h2>Acceptance</h2>
    <p>By downloading or using MannMitra, you agree to these Terms and our <a href="{{ route('privacy.policy') }}">Privacy Policy</a>. If you do not agree, please do not use the app.</p>
</section>

<section class="sec">
    <h2>Eligibility</h2>
    <p>You must be <strong>18 or older</strong> <mark class="ph">[CONFIRM]</mark> and able to enter into a binding contract under Indian law.</p>
</section>

<section class="sec">
    <h2>Not medical or emergency care</h2>
    <div class="callout danger">
        <div class="ic">🚨</div>
        <div>
            <strong>In an emergency or if you are thinking of harming yourself, call your local emergency number immediately, or contact one of these helplines:</strong>
            <div class="helplines">
                <div class="helpline"><small>iCall</small><b>9152987821</b><span>Mon–Sat, 10 AM–8 PM</span></div>
                <div class="helpline"><small>AASRA</small><b>9820466726</b><span>24×7</span></div>
                <div class="helpline"><small>Vandrevala Foundation</small><b>1860 266 2345</b><span>24×7</span></div>
                <div class="helpline"><small>Kiran</small><b>1800-599-0019</b><span>Toll-free</span></div>
            </div>
        </div>
    </div>
    <ul>
        <li>MannMitra provides emotional support and wellbeing tools. <strong>The AI companion is not a doctor, therapist or counsellor, and its responses are not medical advice, diagnosis or treatment.</strong></li>
        <li><strong>Listeners</strong> provide supportive conversation and are not necessarily licensed clinicians. <strong>Psychiatrists and professionals</strong> provide consultations as independent practitioners <mark class="ph">[CONFIRM]</mark>.</li>
        <li>Keyword-based crisis detection may miss some situations. Do not rely on MannMitra to detect or respond to an emergency.</li>
        <li>Never ignore or delay professional advice because of something you read or heard in the app.</li>
    </ul>
</section>

<section class="sec">
    <h2>Your account</h2>
    <p>You can use the app as an anonymous guest or by registering. You are responsible for your device and account. Provide accurate information if you register. Tell us if you suspect unauthorized use.</p>
</section>

<section class="sec">
    <h2>Our services</h2>
    <div class="cards">
        <div class="card"><div class="emoji">🤖</div><h4>AI Companion</h4><p>Text and voice conversations, mood tracking, journaling, AI reflections and CBT exercises.</p></div>
        <div class="card"><div class="emoji">👂</div><h4>Listener sessions</h4><p>Paid chat sessions with human listeners, per ticket.</p></div>
        <div class="card"><div class="emoji">🩺</div><h4>Appointments</h4><p>Paid audio or video consultations with professionals, subject to availability.</p></div>
        <div class="card"><div class="emoji">⭐</div><h4>Subscriptions</h4><p>Monthly or yearly plans that unlock premium features.</p></div>
        <div class="card"><div class="emoji">💚</div><h4>WhatsApp service</h4><p>An AI chat bot on WhatsApp, subject to WhatsApp's own terms.</p></div>
    </div>
    <p>We may change, suspend or discontinue any feature at any time.</p>
</section>

<section class="sec">
    <h2>Payments, pricing and renewal</h2>
    <div class="prices">
        <div class="price"><small>Listener ticket</small><div class="amt">₹<mark class="ph">[99]</mark></div></div>
        <div class="price"><small>Appointment</small><div class="amt">₹<mark class="ph">[499]</mark></div></div>
        <div class="price"><small>Monthly plan</small><div class="amt">₹<mark class="ph">[99]</mark></div></div>
        <div class="price"><small>Yearly plan</small><div class="amt">₹<mark class="ph">[799]</mark></div></div>
    </div>
    <ul>
        <li>Prices are in INR and shown before payment. Prices may change, but not for payments already made.</li>
        <li>Payments are processed by <strong>Razorpay</strong>. We don't store your payment credentials.</li>
        <li>Subscriptions run for one month or one year from purchase. <mark class="ph">[STATE WHETHER THEY AUTO-RENEW; the current implementation activates fixed-period plans, so this assumes no auto-renewal]</mark></li>
        <li>If a payment fails but your account is debited, the bank or gateway normally refunds it within 3–5 working days.</li>
    </ul>
</section>

<section class="sec">
    <h2>Cancellations and refunds</h2>
    <p><mark class="ph">[CONFIRM POLICY. Suggested wording below:]</mark></p>
    <ul>
        <li>You may cancel an appointment before it starts. Refunds for cancellations made at least <mark class="ph">[X]</mark> hours before the appointment are issued to the original payment method within <mark class="ph">[5–7]</mark> working days.</li>
        <li>No refund for missed appointments or for sessions already delivered.</li>
        <li>If we or the professional cancel or fail to attend, you get a full refund or a free reschedule.</li>
        <li>Subscription fees are non-refundable once the plan is activated, except where required by law.</li>
        <li>Appointments not paid for within the allowed time, and appointments that expire, are cancelled automatically.</li>
    </ul>
</section>

<section class="sec">
    <h2>Acceptable use</h2>
    <p>You agree not to:</p>
    <ul>
        <li>harass, abuse or threaten listeners, professionals or others</li>
        <li>share unlawful, hateful or sexually explicit content</li>
        <li>impersonate anyone</li>
        <li>reverse-engineer, scrape or misuse the app or its APIs</li>
        <li>use the app to harm yourself or others</li>
        <li>record sessions without permission</li>
        <li>use the app for any unlawful purpose or in a way that disrupts our systems</li>
    </ul>
    <p>We may suspend or terminate access if you break these rules.</p>
</section>

<section class="sec">
    <h2>Your content</h2>
    <p>You own the content you submit (messages, journals, mood entries). You give us a limited licence to store, process and transmit it, including through our AI and infrastructure providers, to provide the service. See the <a href="{{ route('privacy.policy') }}">Privacy Policy</a> for details.</p>
</section>

<section class="sec">
    <h2>AI limitations</h2>
    <p>AI responses are generated automatically and can be incorrect, incomplete or inappropriate. You use them at your own discretion.</p>
</section>

<section class="sec">
    <h2>Intellectual property</h2>
    <p>The app, its design, text, animations and software belong to <mark class="ph">[COMPANY]</mark> or its licensors. You may not copy, modify or distribute them without written permission.</p>
</section>

<section class="sec">
    <h2>Third-party services</h2>
    <p>The app relies on third parties (OpenAI, Google Firebase, Razorpay, WhatsApp/Whapi). Their terms apply to their services, and we are not responsible for their availability or conduct.</p>
</section>

<section class="sec">
    <h2>Disclaimers</h2>
    <p>The app is provided "as is" and "as available", without warranties of any kind, to the extent permitted by law. We do not guarantee uninterrupted or error-free service or any particular health outcome.</p>
</section>

<section class="sec">
    <h2>Limitation of liability</h2>
    <p>To the extent permitted by law, <mark class="ph">[COMPANY]</mark> is not liable for indirect, incidental or consequential damages, or for decisions you make based on AI or listener responses. Our total liability for any claim is limited to the amount you paid us in the 12 months before the claim. Nothing here excludes liability that cannot be excluded under Indian law.</p>
</section>

<section class="sec">
    <h2>Indemnity</h2>
    <p>You agree to indemnify us against claims arising from your misuse of the app or breach of these Terms.</p>
</section>

<section class="sec">
    <h2>Termination</h2>
    <p>You can stop using the app and request deletion of your data at any time. We may suspend or end your access for breach of these Terms or for legal reasons.</p>
</section>

<section class="sec">
    <h2>Changes to these terms</h2>
    <p>We may update these Terms. We will notify you in the app and update the date above. Continued use means you accept the changes.</p>
</section>

<section class="sec">
    <h2>Governing law and disputes</h2>
    <p>These Terms are governed by the laws of India. Courts in <mark class="ph">[CITY, STATE]</mark> have exclusive jurisdiction <mark class="ph">[optionally: after attempting resolution through arbitration]</mark>.</p>
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
