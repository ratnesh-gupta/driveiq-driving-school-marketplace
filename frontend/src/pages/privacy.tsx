import { Link } from "wouter";
import { PublicLayout } from "@/components/layout/public-layout";

export default function PrivacyPage() {
  return (
    <PublicLayout>
      <div className="container mx-auto px-4 py-12 max-w-3xl prose prose-neutral dark:prose-invert">
        <h1>Privacy Policy</h1>
        <p className="text-muted-foreground text-sm">Last updated: 22 September 2026</p>

        <p>
          DriveIQ (&quot;we&quot;, &quot;us&quot;) operates a driving-school marketplace and school operations
          platform. This policy explains what personal data we collect, why, and your choices.
          It is written with India’s Digital Personal Data Protection (DPDP) Act principles in mind.
        </p>

        <h2>1. Data we collect</h2>
        <ul>
          <li>
            <strong>Account data</strong> — name, email, password (hashed), role (learner, school,
            instructor, admin).
          </li>
          <li>
            <strong>School & training data</strong> — inquiries, learner profiles, schedules,
            attendance, progress, messages, invoices/payments records as needed to run the service.
          </li>
          <li>
            <strong>Location (optional)</strong> — approximate coordinates only when you tap
            &quot;Use my location&quot; to find nearby schools. We do not continuously track your device.
          </li>
          <li>
            <strong>Technical data</strong> — IP address (e.g. audit logs), browser storage for auth
            token and preferences.
          </li>
        </ul>

        <h2>2. How we use data</h2>
        <ul>
          <li>Provide search, booking, training, messaging, and payment features</li>
          <li>Authenticate users and enforce role-based access</li>
          <li>Improve reliability and prevent abuse (rate limits, audit logs)</li>
          <li>Respond to support and data-rights requests</li>
        </ul>

        <h2>3. Cookies & local storage</h2>
        <p>
          DriveIQ primarily uses <strong>local storage</strong>, not advertising cookies:
        </p>
        <ul>
          <li>
            <code>driveiq_auth_token</code> — session token after login (essential)
          </li>
          <li>Theme preference (essential / functional)</li>
          <li>
            <code>driveiq_cookie_consent</code> — your consent choice
          </li>
        </ul>
        <p>
          We do not currently load third-party advertising or cross-site tracking cookies. If
          optional analytics are added later, they will only run after you choose &quot;Accept&quot; on the
          consent banner.
        </p>

        <h2>4. Location</h2>
        <p>
          Location access is <strong>opt-in</strong>. The browser permission prompt appears only after
          you choose to use nearby search. Coordinates are sent to our API for ranking schools by
          distance for that request. We do not sell location data or build a continuous location
          history profile.
        </p>

        <h2>5. Sharing</h2>
        <p>
          School operators see data needed to serve their learners (e.g. inquiries, schedules).
          We do not sell personal data. Processors (hosting, email, payment gateways) may process
          data under contract. Payment card numbers are not stored by DriveIQ; gateways handle card
          data when live payments are enabled.
        </p>

        <h2>6. Retention</h2>
        <p>
          Account and training records are kept while the account or school relationship is active
          and for a reasonable period afterward for legal, tax, and dispute purposes. You may
          request deletion subject to lawful retention needs.
        </p>

        <h2>7. Your rights</h2>
        <p>Subject to applicable law, you may request:</p>
        <ul>
          <li>Access to / copy of your personal data</li>
          <li>Correction of inaccurate data</li>
          <li>Deletion of data (where not required to be retained)</li>
          <li>Withdrawal of consent for optional processing (e.g. location, non-essential storage)</li>
        </ul>
        <p>
          Submit a request via{" "}
          <Link href="/privacy/data-request">Data rights request</Link> or{" "}
          <Link href="/contact">Contact</Link>.
        </p>

        <h2>8. Security</h2>
        <p>
          We use access controls, school-scoped data isolation, hashed passwords, and HTTPS in
          production. No method of transmission is 100% secure; report concerns via Contact.
        </p>

        <h2>9. Children</h2>
        <p>
          The service is aimed at adults arranging driving training. If you believe a minor’s data
          was submitted inappropriately, contact us to remove it.
        </p>

        <h2>10. Changes</h2>
        <p>
          We may update this policy; the &quot;Last updated&quot; date will change. Continued use after
          material changes means you accept the updated policy where permitted by law.
        </p>

        <h2>11. Contact</h2>
        <p>
          Privacy questions: use the <Link href="/contact">Contact</Link> form or email the address
          published on that page.
        </p>

        <p className="text-sm text-muted-foreground not-prose mt-8">
          Also see <Link href="/terms" className="underline">Terms of Service</Link>.
        </p>
      </div>
    </PublicLayout>
  );
}
