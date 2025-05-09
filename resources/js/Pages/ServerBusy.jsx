import Button from "@/Components/Button";
import { useForm } from "@inertiajs/react";
import React, { useEffect, useState } from "react";
import { useTranslation } from 'react-i18next';

export default function ServerBusy({ lang, referer, merchant_id }) {

    const { t, i18n } = useTranslation();
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (lang === 'en' || lang === 'cn' || lang === 'tw') {
            i18n.changeLanguage(lang);
        } else {
            i18n.changeLanguage('en');
        }
    }, [lang, i18n]);

    const { data, setData, post, processing, errors, reset } = useForm({
        referer: referer,
        merchant_id: merchant_id,
    });

    const returnBack = (e) => {
        e.preventDefault();
        setIsLoading(true);
        post('/return-crm2', {
            preserveScroll: true,
            onSuccess: () => {
                setIsLoading(false);
            }
        });
    }

    return (
        <div className="w-full flex flex-col items-center justify-center gap-5 px-3 md:px-0 min-h-[80vh]">
            <div className="flex flex-col items-center gap-5">
                <div className="w-28">
                    <img src="/assets/trc20.svg" alt="" />
                </div>
                <div className="text-lg font-bold">
                    TT Payment Gateway
                </div>
                <div className="flex flex-col items-center gap-2">
                    {/* <div className="text-sm font-medium">
                        {t('please_ensure')}<span className="font-bold">USDT TRC 20</span>.
                    </div> */}
                    {/* 請確保您發送的代幣是<span className="font-bold">USDT TRC 20</span>. */}
                    <div className="flex flex-col text-sm font-bold text-center text-[#ef4444] w-full">
                        <div className="max-w-80">{t('server_busy')}</div>
                        
                        {/* <div>{t('note')}: {t('remark1')}</div>
                        <div>{t('remark2')}</div> */}
                    </div>
                </div>
            </div>

            <div>
                <Button size="sm" variant="black" onClick={returnBack}>
                    Return
                </Button>
            </div>
        </div>
    )
}