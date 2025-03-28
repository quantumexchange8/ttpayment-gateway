import { SuccessIcon } from "@/Components/Brand";
import Button from "@/Components/Button";
import { useForm } from "@inertiajs/react";
import React, { useEffect, useState } from "react";
import { useTranslation } from 'react-i18next';

export default function Processing({ lang, referer, merchant_id }) {

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
        post('/return-crm', {
            preserveScroll: true,
            onSuccess: () => {
                setIsLoading(false);
            }
        });
    }

    return (
        <div className="w-full flex flex-col items-center justify-center gap-5 px-3 md:px-0 min-h-[80vh]">
            <div className="flex flex-col gap-6 items-center">
                <SuccessIcon/>
                <div className="flex flex-col items-center">
                    <div className=" text-lg font-semibold text-gray-950">
                        {t('success')}!
                    </div>
                    <div className="text-gray-500 text-sm">
                        {t('deposit_success')}.
                    </div>
                </div>
            </div>

            <div>
                <Button size="sm" variant="black" onClick={returnBack}>
                    {t('return')}
                </Button>
            </div>
        </div>
    )
}