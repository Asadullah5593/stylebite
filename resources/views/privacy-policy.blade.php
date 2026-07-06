<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy | {{ config('app.name', 'Stylebite') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="mx-auto max-w-4xl px-6 py-12 sm:px-8 lg:py-16">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Privacy Policy</h1>
            <p class="mt-3 text-sm text-slate-600">Last updated: June 2, 2026</p>

            <div class="prose prose-slate mt-8 max-w-none">
                <p>
                    This Privacy Policy explains how we collect, use, disclose, and protect your information when you use
                    our website and related services.
                </p>

                <h2>1. Information We Collect</h2>
                <p>We may collect the following categories of information:</p>
                <ul>
                    <li>Account information such as name, email address, and profile details.</li>
                    <li>Content you upload or share, including posts, comments, and media files.</li>
                    <li>Usage information such as pages visited, actions taken, and device/browser details.</li>
                    <li>Communications you send to us, including support inquiries and feedback.</li>
                </ul>

                <h2>2. How We Use Your Information</h2>
                <p>We use your information to:</p>
                <ul>
                    <li>Provide, operate, and maintain our services.</li>
                    <li>Personalize your experience and improve platform performance.</li>
                    <li>Communicate with you about updates, security alerts, and support.</li>
                    <li>Detect, prevent, and address fraud, abuse, and policy violations.</li>
                    <li>Comply with legal obligations and enforce our terms.</li>
                </ul>

                <h2>3. How We Share Information</h2>
                <p>
                    We do not sell your personal information. We may share information with service providers, legal
                    authorities when required by law, or in connection with a merger, acquisition, or asset transfer.
                </p>

                <h2>4. Data Retention</h2>
                <p>
                    We retain personal information only as long as necessary for the purposes described in this policy,
                    unless a longer retention period is required by law.
                </p>

                <h2>5. Security</h2>
                <p>
                    We implement reasonable administrative, technical, and organizational safeguards to protect your
                    information. However, no method of transmission over the internet is completely secure.
                </p>

                <h2>6. Your Choices and Rights</h2>
                <p>Depending on your location, you may have rights to access, update, delete, or restrict use of your data.</p>
                <p>
                    You can request these actions by contacting us. We may need to verify your identity before processing
                    certain requests.
                </p>

                <h2>7. Third-Party Links</h2>
                <p>
                    Our website may contain links to third-party websites. We are not responsible for the privacy practices
                    or content of those sites.
                </p>

                <h2>8. Children's Privacy</h2>
                <p>
                    Our services are not directed to children under 13, and we do not knowingly collect personal information
                    from children under 13.
                </p>

                <h2>9. Changes to This Policy</h2>
                <p>
                    We may update this Privacy Policy from time to time. If we make material changes, we will update the
                    "Last updated" date and post the revised policy on this page.
                </p>

                <h2>10. Contact Us</h2>
                <p>
                    If you have questions about this Privacy Policy, please contact us at
                    <a href="mailto:support@stylebite.com">support@stylebite.com</a>.
                </p>
            </div>
        </div>
    </main>
</body>
</html>
