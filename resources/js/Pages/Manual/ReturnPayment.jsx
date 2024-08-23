import { SuccessIcon } from "@/Components/Brand";
import Button from "@/Components/Button";
import { useForm } from "@inertiajs/react";
import React, { useState } from "react";

export default function ReturnPayment({ datas, total_amount, transaction, storedToken, merchant_id, referer }) {
    
    const [isLoading, setIsLoading] = useState(false);

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
                            Success!
                        </div>
                        <div className="text-gray-500 text-sm">
                            Your deposit is now being processed.
                        </div>
                    </div>
                </div>
                <form onSubmit={submit} >
                    <Button type="submit" size="sm" variant="success" className="w-full flex justify-center">
                        <span className="text-sm font-semibold">
                            Return
                        </span>
                    </Button>
                </form>
            </div>
        </div>
    )
}