<script setup lang="ts">
import LegalSection from '@/components/legal/LegalSection.vue';
import LegalShell from '@/components/legal/LegalShell.vue';
import { Head } from '@inertiajs/vue3';

const toc = [
    { id: 'overview', label: 'Overview' },
    { id: 'data-collected', label: 'Information We Collect' },
    { id: 'how-we-use', label: 'How We Use It' },
    { id: 'sharing', label: 'How We Share It' },
    { id: 'cookies', label: 'Cookies & Tracking' },
    { id: 'security', label: 'Data Security' },
    { id: 'retention', label: 'Data Retention' },
    { id: 'your-rights', label: 'Your Rights' },
    { id: 'third-parties', label: 'Third-Party Services' },
    { id: 'children', label: "Children's Privacy" },
    { id: 'changes', label: 'Policy Changes' },
    { id: 'contact', label: 'Contact Us' },
];
</script>

<template>
    <Head title="Privacy Policy" />
    <LegalShell
        title="Privacy Policy"
        subtitle="We respect your privacy and are committed to protecting your personal data."
        updated="Last updated: April 17, 2026 · Effective: April 17, 2026"
        :toc="toc"
    >
        <div class="rounded-xl border border-primary/25 bg-primary/10 p-4 text-sm text-foreground">
            This Privacy Policy describes how <strong>Punchlist Labs</strong> ("we," "our," or "us") collects, uses, and shares information when you
            use the <strong>RoutePilot</strong> platform and related services (collectively, the "Service"). By accessing or using the Service, you
            agree to the practices described in this policy.
        </div>

        <LegalSection :n="1" id="overview" title="Overview">
            <p>
                RoutePilot is a software-as-a-service (SaaS) platform that enables pool and spa service companies ("Subscribers") to manage their
                operations, including route planning, chemical tracking, customer management, and technician coordination.
            </p>
            <p>
                If you are a homeowner accessing a pool service company's customer portal built on RoutePilot, your data is handled by that service
                company under their own privacy policy. We process it on their behalf as a data processor.
            </p>
            <p>
                We do not sell your personal information to third parties. We are not in the business of advertising — we are in the business of
                building great software.
            </p>
        </LegalSection>

        <LegalSection :n="2" id="data-collected" title="Information We Collect">
            <p>
                <strong>Account Information.</strong> When you register, we collect your name, email address, password (stored as a secure hash), and
                company name. We use this to create and manage your account.
            </p>
            <p>
                <strong>Business Data.</strong> This includes customer records, pool information, chemical readings, service visit reports, photos,
                route schedules, inventory, and billing records that you create through the platform. This data is yours.
            </p>
            <p>
                <strong>Location Data.</strong> We collect addresses and GPS coordinates for service locations to enable route optimization and map
                features. We also use your pool location to fetch local weather forecasts.
            </p>
            <p>
                <strong>Usage Data.</strong> We collect standard server logs including IP address, browser type, pages visited, and timestamps. This
                helps us diagnose issues and improve performance.
            </p>
            <p>
                <strong>AI Conversations.</strong> When you use the AI Assistant, your messages and the AI's responses are stored in your account to
                maintain conversation history. We do not use your conversations to train AI models.
            </p>
            <p>
                <strong>Customer Portal Data.</strong> When homeowners access your customer portal, submit contact forms, or request services, that
                data is stored in your account's database. You are the data controller for this information.
            </p>
            <p>
                <strong>Communications.</strong> If you contact us via email or support channels, we retain those communications to assist you and
                improve our service.
            </p>
        </LegalSection>

        <LegalSection :n="3" id="how-we-use" title="How We Use Your Information">
            <p>We use your information to:</p>
            <ul>
                <li>Provide, maintain, and improve the RoutePilot platform</li>
                <li>Optimize service routes and calculate arrival estimates</li>
                <li>Perform water chemistry analysis and generate treatment recommendations</li>
                <li>Send service notifications, invoices, and system emails on your behalf</li>
                <li>Fetch weather data relevant to your service locations</li>
                <li>Power the AI Assistant with your business context</li>
                <li>Generate your public-facing landing page and customer portal</li>
                <li>Detect and prevent fraud, abuse, and security threats</li>
                <li>Communicate with you about your account and platform updates</li>
            </ul>
            <p>We do not use your business data for advertising, profiling, or any purpose unrelated to delivering the Service.</p>
        </LegalSection>

        <LegalSection :n="4" id="sharing" title="How We Share Your Information">
            <p><strong>We do not sell your data.</strong> We share information only in these limited circumstances:</p>
            <ul>
                <li>
                    <strong>AI Providers:</strong> Chat messages are sent to Anthropic (Claude) or OpenAI for processing. These providers do not
                    retain your data for training. Your API key (if provided) is used directly.
                </li>
                <li>
                    <strong>Weather Services:</strong> Pool location coordinates are sent to Open-Meteo (a free, open-source weather API) to fetch
                    forecasts. No personally identifiable information is transmitted.
                </li>
                <li>
                    <strong>Map Services:</strong> If you enable Google Maps, location data is sent to Google for map rendering. Otherwise, we use
                    OpenStreetMap (no data sharing required).
                </li>
                <li><strong>Geocoding:</strong> Addresses are sent to OpenStreetMap Nominatim for latitude/longitude conversion.</li>
                <li>
                    <strong>Email Delivery:</strong> We use your configured SMTP provider (or our platform default) to send emails on your behalf.
                </li>
                <li>
                    <strong>Legal Compliance:</strong> We may disclose information if required by law, court order, or to protect the rights, safety,
                    or property of our users or the public.
                </li>
            </ul>
            <p>We do not share your data with advertisers, data brokers, or any third party for their own marketing purposes.</p>
        </LegalSection>

        <LegalSection :n="5" id="cookies" title="Cookies &amp; Tracking">
            <p>
                We use essential cookies for authentication and session management. We use localStorage for theme preferences (dark/light mode) and UI
                state. We do not use third-party analytics, advertising cookies, or tracking pixels.
            </p>
            <p>If you enable Google Maps on your account, Google may set its own cookies subject to Google's privacy policy.</p>
        </LegalSection>

        <LegalSection :n="6" id="security" title="Data Security">
            <p>We implement industry-standard security measures including:</p>
            <ul>
                <li>All data transmitted over HTTPS (TLS encryption)</li>
                <li>Passwords stored using bcrypt hashing (never stored in plain text)</li>
                <li>Database-backed sessions with configurable lifetime</li>
                <li>CSRF protection on all state-changing requests</li>
                <li>Role-based access control (tenant admin, agent, customer)</li>
                <li>Tenant data isolation — each company's data is logically separated</li>
                <li>API keys and sensitive settings encrypted at rest</li>
            </ul>
            <p>
                No system is 100% secure. If you discover a vulnerability, please contact us at
                <a href="mailto:security@punchlistlabs.com">security@punchlistlabs.com</a>.
            </p>
        </LegalSection>

        <LegalSection :n="7" id="retention" title="Data Retention">
            <p>
                We retain your data for as long as your account is active. If you cancel your subscription, your data is retained for 90 days in case
                you reactivate, then permanently deleted.
            </p>
            <p>
                You may request immediate deletion of your data at any time by contacting us. AI conversation history is retained for 24 hours per
                session and limited to the last 200 messages.
            </p>
        </LegalSection>

        <LegalSection :n="8" id="your-rights" title="Your Rights">
            <p>Depending on your jurisdiction, you may have the right to:</p>
            <ul>
                <li>Access the personal data we hold about you</li>
                <li>Request correction of inaccurate data</li>
                <li>Request deletion of your data</li>
                <li>Export your data in a portable format</li>
                <li>Object to or restrict certain processing</li>
                <li>Withdraw consent where processing is based on consent</li>
            </ul>
            <p>To exercise these rights, contact us at <a href="mailto:privacy@punchlistlabs.com">privacy@punchlistlabs.com</a>.</p>
        </LegalSection>

        <LegalSection :n="9" id="third-parties" title="Third-Party Services">
            <p>RoutePilot integrates with the following third-party services. Each has its own privacy policy:</p>
            <ul>
                <li><strong>Anthropic (Claude AI)</strong> — AI assistant conversations</li>
                <li><strong>OpenAI (ChatGPT)</strong> — Alternative AI provider (tenant-configurable)</li>
                <li><strong>Open-Meteo</strong> — Weather forecast data (free, open-source, no API key)</li>
                <li><strong>Google Maps</strong> — Map rendering (optional, tenant-configurable)</li>
                <li><strong>OpenStreetMap / CARTO</strong> — Default map tiles (free, no tracking)</li>
                <li><strong>Google OAuth</strong> — Optional social login</li>
            </ul>
            <p>
                Payment processing is handled by your own configured provider (PayPal, Stripe, Venmo, Zelle, etc.) — RoutePilot does not process
                payments directly.
            </p>
        </LegalSection>

        <LegalSection :n="10" id="children" title="Children's Privacy">
            <p>
                RoutePilot is designed for business use by pool service professionals. We do not knowingly collect personal information from children
                under 13. If you believe a child has provided us with personal data, please contact us and we will promptly delete it.
            </p>
        </LegalSection>

        <LegalSection :n="11" id="changes" title="Policy Changes">
            <p>
                We may update this Privacy Policy from time to time. When we make material changes, we will notify you by email or through a prominent
                notice on the platform. Your continued use of the Service after changes take effect constitutes acceptance of the updated policy.
            </p>
        </LegalSection>

        <LegalSection :n="12" id="contact" title="Contact Us">
            <p>If you have questions about this Privacy Policy or our data practices, contact us at:</p>
            <p>
                <strong>Punchlist Labs</strong><br />
                Email: <a href="mailto:privacy@punchlistlabs.com">privacy@punchlistlabs.com</a><br />
                Web: <a href="https://punchlistlabs.com" target="_blank" rel="noopener">punchlistlabs.com</a>
            </p>
        </LegalSection>
    </LegalShell>
</template>
