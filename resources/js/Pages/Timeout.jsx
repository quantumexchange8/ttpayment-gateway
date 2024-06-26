import Button from "@/Components/Button";
import { useForm } from "@inertiajs/react";
import React, { useState } from "react";

export default function SessionTimeOut() {
    const [isLoading, setIsLoading] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
    })

    const submit = (e) => {
        e.preventDefault();
        setIsLoading(true);
        post('/returnSession', {
            preserveScroll: true,
            onSuccess: () => {
                setIsLoading(false);
            }
        })
    }
    
    return (
        
        <div className="w-full flex flex-col items-center justify-center gap-5 min-h-screen">

            <div className=" text-xxl font-semibold">
               Session Timeout
            </div>
            <form onSubmit={submit}>
                <Button type="submit" size="sm" variant="success" className="w-full flex justify-center">
                    <span className="text-sm font-semibold">
                        Return
                    </span>
                </Button>
            </form>
        </div>
    
    )
}