import React, { useState } from 'react';
import { PaymentIframe } from './PaymentIframe';

export const CheckoutFlow: React.FC = () => {
    const [step, setStep] = useState(1);
    const [paymentToken, setPaymentToken] = useState<string | null>(null);
    const [iframeUrl, setIframeUrl] = useState<string | null>(null);

    const initializePayment = async () => {
        try {
            const response = await fetch('/api/v1/storefront/checkout/initialize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ store_id: 1, amount: 100.50 }) // mock data
            });
            const data = await response.json();
            if (data.status === 'success') {
                setPaymentToken(data.token);
                setIframeUrl(data.iframe_url);
                setStep(3);
            }
        } catch (error) {
            console.error('Failed to initialize payment', error);
        }
    };

    return (
        <div className="max-w-4xl mx-auto py-10 px-4">
            <h1 className="text-3xl font-bold mb-6">Checkout</h1>
            <div className="flex gap-4 mb-8 border-b pb-4">
                <div className={`flex-1 ${step === 1 ? 'font-bold text-blue-600' : 'text-gray-500'}`}>1. Shipping Info</div>
                <div className={`flex-1 ${step === 2 ? 'font-bold text-blue-600' : 'text-gray-500'}`}>2. Shipping Method</div>
                <div className={`flex-1 ${step === 3 ? 'font-bold text-blue-600' : 'text-gray-500'}`}>3. Payment</div>
            </div>

            {step === 1 && (
                <div className="space-y-4">
                    <h2 className="text-xl font-semibold">Contact & Shipping Address</h2>
                    <input type="text" placeholder="Full Name" className="w-full p-2 border rounded" />
                    <input type="email" placeholder="Email Address" className="w-full p-2 border rounded" />
                    <textarea placeholder="Full Address" className="w-full p-2 border rounded" rows={3}></textarea>
                    <button onClick={() => setStep(2)} className="bg-blue-600 text-white px-6 py-2 rounded">Continue to Shipping</button>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h2 className="text-xl font-semibold">Select Shipping Method</h2>
                    <div className="p-4 border rounded cursor-pointer hover:bg-gray-50 border-blue-500 bg-blue-50">
                        <div className="font-bold">Yurtiçi Kargo</div>
                        <div className="text-sm text-gray-500">2-3 Business Days</div>
                    </div>
                    <div className="flex gap-4">
                        <button onClick={() => setStep(1)} className="bg-gray-200 text-gray-800 px-6 py-2 rounded">Back</button>
                        <button onClick={initializePayment} className="bg-blue-600 text-white px-6 py-2 rounded">Continue to Payment</button>
                    </div>
                </div>
            )}

            {step === 3 && iframeUrl && (
                <div className="space-y-4">
                    <h2 className="text-xl font-semibold">Complete Payment</h2>
                    <PaymentIframe url={iframeUrl} token={paymentToken!} />
                    <button onClick={() => setStep(2)} className="text-sm text-gray-500 underline">Back to Shipping</button>
                </div>
            )}
        </div>
    );
};
