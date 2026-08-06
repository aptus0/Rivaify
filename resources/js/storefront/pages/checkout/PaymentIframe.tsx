import React from 'react';

interface PaymentIframeProps {
    url: string;
    token: string;
}

export const PaymentIframe: React.FC<PaymentIframeProps> = ({ url, token }) => {
    // In a real scenario, this would post the token to the iframe source URL
    // For PayTR/Iyzico, they usually provide a script that mounts the iframe,
    // or you render an iframe that loads their hosted payment page.
    
    return (
        <div className="border rounded bg-gray-50 p-4">
            <div className="w-full h-[400px] flex items-center justify-center border-dashed border-2 border-gray-300">
                <div className="text-center">
                    <p className="text-gray-500 mb-2">Secure Payment Gateway (Mock)</p>
                    <p className="text-sm text-gray-400">Token: {token}</p>
                    <iframe 
                        src={url} 
                        width="100%" 
                        height="300" 
                        title="Secure Payment"
                        className="mt-4 bg-white border"
                    />
                </div>
            </div>
        </div>
    );
};
