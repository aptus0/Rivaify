import { Navbar } from './components/Navbar';
import { Hero } from './components/Hero';
import { Countdown } from './components/Countdown';
import { DashboardPreview } from './components/DashboardPreview';
import { HowItWorks } from './components/HowItWorks';
import { Features } from './components/Features';
import { Integrations } from './components/Integrations';
import { Themes } from './components/Themes';
import { WhyRivaify } from './components/WhyRivaify';
import { EarlyAccess } from './components/EarlyAccess';
import { Footer } from './components/Footer';

export function App() {
  return (
    <div className="min-h-screen bg-surface font-sans text-dark">
      <Navbar />
      <main>
        <Hero />
        <DashboardPreview />
        <HowItWorks />
        <Features />
        <Integrations />
        <Themes />
        <WhyRivaify />
        <Countdown />
        <EarlyAccess />
      </main>
      <Footer />
    </div>
  );
}
