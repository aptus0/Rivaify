import { Navigation } from './components/marketing/Navigation/Navigation';
import { Hero } from './components/marketing/Hero/Hero';
import { SocialCommerce } from './components/marketing/SocialCommerce/SocialCommerce';
import { ControlCenter } from './components/marketing/ControlCenter/ControlCenter';
import { StoreBuilder } from './components/marketing/StoreBuilder/StoreBuilder';
import { ThemeShowcase } from './components/marketing/ThemeShowcase/ThemeShowcase';
import { CheckoutPreview } from './components/marketing/CheckoutPreview/CheckoutPreview';
import { MobileStore } from './components/marketing/MobileStore/MobileStore';
import { Analytics } from './components/marketing/Analytics/Analytics';
import { AIAssistant } from './components/marketing/AIAssistant/AIAssistant';
import { Integrations } from './components/marketing/Integrations/Integrations';
import { CustomDomain } from './components/marketing/CustomDomain/CustomDomain';
import { Performance } from './components/marketing/Performance/Performance';
import { Trust } from './components/marketing/Trust/Trust';
import { FinalCTA } from './components/marketing/FinalCTA/FinalCTA';
import { Footer } from './components/marketing/Footer/Footer';

export function App() {
  return (
    <div className="min-h-screen bg-white font-sans text-dark">
      <Navigation />
      <main>
        <Hero />
        <SocialCommerce />
        <ControlCenter />
        <StoreBuilder />
        <ThemeShowcase />
        <CheckoutPreview />
        <MobileStore />
        <Analytics />
        <AIAssistant />
        <Integrations />
        <CustomDomain />
        <Performance />
        <Trust />
        <FinalCTA />
      </main>
      <Footer />
    </div>
  );
}
