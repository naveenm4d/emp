import { Head } from '@inertiajs/react';

import { DashboardDemo } from '@/components/site/home/dashboard-demo';
import { FactsBand } from '@/components/site/home/facts-band';
import { Faq } from '@/components/site/home/faq';
import { FeaturesBento } from '@/components/site/home/features-bento';
import { FinalCta } from '@/components/site/home/final-cta';
import { Footer } from '@/components/site/home/footer';
import { Hero } from '@/components/site/home/hero';
import { Nav } from '@/components/site/home/nav';
import { OccasionsMarquee } from '@/components/site/home/occasions-marquee';
import { Pricing } from '@/components/site/home/pricing';
import { Story } from '@/components/site/home/story';
import { TemplatesShowcase } from '@/components/site/home/templates-showcase';

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
