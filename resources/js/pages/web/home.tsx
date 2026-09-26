import { Head } from '@inertiajs/react';

import { DashboardDemo } from '@/components/web/home/dashboard-demo';
import { FactsBand } from '@/components/web/home/facts-band';
import { Faq } from '@/components/web/home/faq';
import { FeaturesBento } from '@/components/web/home/features-bento';
import { FinalCta } from '@/components/web/home/final-cta';
import { Footer } from '@/components/web/home/footer';
import { Hero } from '@/components/web/home/hero';
import { Nav } from '@/components/web/home/nav';
import { OccasionsMarquee } from '@/components/web/home/occasions-marquee';
import { Pricing } from '@/components/web/home/pricing';
import { Story } from '@/components/web/home/story';
import { TemplatesShowcase } from '@/components/web/home/templates-showcase';

/** Marketing homepage: static, all copy in components/home/content.ts. */
export default function Home({ dashboardUrl }: { dashboardUrl: string }) {
    return (
        <div className="home font-sans antialiased">
            <Head title="Digital invitations on WhatsApp">
                <meta
                    name="description"
                    content="Design beautiful digital invitations, send them on WhatsApp and track every RSVP in real time. Free for your first event."
                />
            </Head>
            <Nav dashboardUrl={dashboardUrl} />
            <main>
                <Hero dashboardUrl={dashboardUrl} />
                <OccasionsMarquee />
                <FeaturesBento />
                <Story />
                <DashboardDemo />
                <TemplatesShowcase />
                <FactsBand />
                <Pricing dashboardUrl={dashboardUrl} />
                <Faq />
            </main>
            <FinalCta dashboardUrl={dashboardUrl} />
            <Footer dashboardUrl={dashboardUrl} />
        </div>
    );
}
