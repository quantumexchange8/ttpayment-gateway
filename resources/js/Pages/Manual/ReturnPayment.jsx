import { SuccessIcon } from "@/Components/Brand";
import Button from "@/Components/Button";
import { useForm } from "@inertiajs/react";
import React, { useEffect, useState } from "react";
import { useTranslation } from 'react-i18next';

export default function ReturnPayment({ datas, total_amount, transaction, storedToken, merchant_id, referer, lang }) {
    
    const [isLoading, setIsLoading] = useState(false);
    const { t, i18n } = useTranslation();

    useEffect(() => {
        if (lang === 'en' || lang === 'cn' || lang === 'tw') {
            i18n.changeLanguage(lang);
        } else {
            i18n.changeLanguage('en');
        }
    }, [lang, i18n]);

    const { data, setData, post, processing, errors, reset } = useForm({
        transaction: transaction,
        storedToken: storedToken,
        merchant_id: merchant_id,
        referer: referer,
    })

    const submit = (e) => {
        e.preventDefault();
        setIsLoading(true);
        post('/returnUrl', {
            preserveScroll: true,
            onSuccess: () => {
                setIsLoading(false);
            }
        })
    }

    return (
        <div className="w-full flex flex-col items-center justify-center gap-5 min-h-screen">
            <div className="max-w-[490px] w-full flex flex-col items-center gap-8">
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
                <form onSubmit={submit} >
                    <Button type="submit" size="sm" variant="success" className="w-full flex justify-center" disabled={processing}>
                        <span className="text-sm font-semibold">
                            {t('return')}
                        </span>
                    </Button>
                </form>
            </div>
        </div>
    )
}