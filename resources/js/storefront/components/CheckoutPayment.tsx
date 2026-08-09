import { useEffect, useRef } from "react";
import { CreditCard, LockKeyhole, ShieldCheck } from "lucide-react";

type PaytrWindow = Window & {
    iFrameResize?: (
        options: { checkOrigin: string[]; heightCalculationMethod: string },
        target: HTMLIFrameElement,
    ) => void;
};

export function CheckoutPayment({
    submitting,
    iframeUrl,
    onPay,
}: {
    submitting: boolean;
    iframeUrl: string | null;
    onPay: () => void;
}) {
    const iframeRef = useRef<HTMLIFrameElement>(null);

    useEffect(() => {
        if (!iframeUrl) return;
        const resize = () => {
            if (iframeRef.current) {
                (window as PaytrWindow).iFrameResize?.(
                    {
                        checkOrigin: ["https://www.paytr.com"],
                        heightCalculationMethod: "bodyScroll",
                    },
                    iframeRef.current,
                );
            }
        };
        const existing = document.querySelector<HTMLScriptElement>(
            "script[data-paytr-iframe-resizer]",
        );
        if (existing) {
            if ((window as PaytrWindow).iFrameResize) resize();
            else existing.addEventListener("load", resize, { once: true });

            return () => existing.removeEventListener("load", resize);
        }

        const script = document.createElement("script");
        script.src = "https://www.paytr.com/js/iframeResizer.min.js";
        script.async = true;
        script.dataset.paytrIframeResizer = "true";
        script.addEventListener("load", resize, { once: true });
        document.head.appendChild(script);

        return () => script.removeEventListener("load", resize);
    }, [iframeUrl]);

    if (iframeUrl)
        return (
            <section className="space-y-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold text-dark">
                            Güvenli kart ödemesi
                        </h2>
                        <p className="mt-1 text-sm text-muted">
                            Kart bilgilerinizi aşağıdaki güvenli alana girin.
                        </p>
                    </div>
                    <span className="flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        <ShieldCheck size={14} /> PayTR güvencesi
                    </span>
                </div>
                <div className="overflow-hidden rounded-xl border border-border bg-white">
                    <iframe
                        ref={iframeRef}
                        src={iframeUrl}
                        title="PayTR güvenli ödeme formu"
                        className="min-h-[680px] w-full"
                        scrolling="auto"
                        allow="payment"
                        referrerPolicy="strict-origin-when-cross-origin"
                    />
                </div>
                <div className="flex items-center justify-center gap-2 text-xs text-muted">
                    <LockKeyhole size={13} />
                    256-bit SSL ile şifrelenen kart bilgileriniz Rivaify
                    tarafından saklanmaz.
                </div>
            </section>
        );
    return (
        <section className="space-y-4">
            <div>
                <h2 className="text-lg font-semibold text-dark">Ödeme</h2>
                <p className="mt-1 text-sm text-muted">
                    Siparişini güvenle tamamla.
                </p>
            </div>
            <button
                type="button"
                className="flex w-full items-center gap-3 rounded-xl border-2 border-primary bg-surface-orange p-4 text-left"
            >
                <span className="rounded-lg bg-white p-2 text-primary shadow-sm">
                    <CreditCard size={21} />
                </span>
                <span className="flex-1">
                    <span className="block font-semibold text-dark">
                        Banka veya kredi kartı
                    </span>
                    <span className="block text-sm text-muted">
                        Visa, Mastercard ve Troy · Taksit seçenekleri PayTR
                        tarafından sunulur
                    </span>
                </span>
                <span className="h-4 w-4 rounded-full border-[5px] border-primary bg-white" />
            </button>
            <div className="grid grid-cols-3 gap-2 text-center text-xs text-muted">
                <span className="rounded-lg bg-app-bg p-3">3D Secure</span>
                <span className="rounded-lg bg-app-bg p-3">PCI DSS</span>
                <span className="rounded-lg bg-app-bg p-3">SSL Güvenli</span>
            </div>
            <button
                disabled={submitting}
                onClick={onPay}
                className="flex w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-3.5 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50"
            >
                <LockKeyhole size={16} />
                {submitting
                    ? "Güvenli ödeme hazırlanıyor..."
                    : "Güvenli ödemeye geç"}
            </button>
            <p className="text-center text-xs leading-5 text-muted">
                Devam ederek Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme
                Formu’nu kabul etmiş olursun.
            </p>
        </section>
    );
}
