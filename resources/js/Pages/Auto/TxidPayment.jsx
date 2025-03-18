import React, { useEffect, useState } from "react";
import { QRCode } from 'react-qrcode-logo';
import { useTranslation } from 'react-i18next';
import Tooltip from "@/Components/Tooltip";
import { CopyIcon } from "@/Components/Brand";
import { useForm } from "@inertiajs/react";
import Input from "@/Components/Input";
import Button from "@/Components/Button";
import InputError from "@/Components/InputError";
import axios from "axios";

export default function TxidPayment({ merchant, merchantClientId, vCode, orderNumber, expirationTime, transaction, tokenAddress, lang, referer, amount }) {

    const { t, i18n } = useTranslation();
    const [tooltipText, setTooltipText] = useState('copy');
    const [returnErrors, setReturnError] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (lang === 'en' || lang === 'cn' || lang === 'tw') {
            i18n.changeLanguage(lang);
        } else {
            i18n.changeLanguage('en');
        }
    }, [lang, i18n]);

    const { data, setData, post, processing, errors, reset } = useForm({
        amount: amount,
        txid: '',
        merchantId: merchant.id,
        transaction: transaction.id,
        referer: referer,
    })

    const handleCopy = (tokenAddress) => {
        const textToCopy = tokenAddress;
        navigator.clipboard.writeText(textToCopy).then(() => {
            setTooltipText('Copied!');
            console.log('Copied to clipboard:', textToCopy);

            // Revert tooltip text back to 'copy' after 2 seconds
            setTimeout(() => {
                setTooltipText('copy');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy:', err);
        });
    };

    const submit = async (e) => {
        e.preventDefault(); // Prevent default form submission

        try {
            setIsLoading(true);    
            setReturnError(null)
            const response = await axios.post('/updateTxid', data);

        } catch (error) {
            if (error.response && error.response.status === 422) {
                // Handle Laravel validation errors
                console.error('Validation Errors:', error.response.data.errors);
                setReturnError(error.response.data.errors); // Store errors in useForm
            } else {
                console.error('Request Error:', error);
            }
        } finally {
            setIsLoading(false);
        }
    }

    console.log(returnErrors)

    return (
        <div className="w-full flex flex-col items-center justify-center gap-5 px-3 md:px-0 min-h-[80vh]">
            <div className="flex flex-col items-center gap-2">
                <div className="w-28">
                    <img src="/assets/trc20.svg" alt="" />
                </div>
                <div className="text-lg font-bold">
                    TT Payment Gateway
                </div>
                <div className="flex flex-col items-center gap-2">
                    <div className="text-sm font-medium">
                        {t('please_ensure')}<span className="font-bold">USDT TRC 20</span>.
                    </div>
                    {/* 請確保您發送的代幣是<span className="font-bold">USDT TRC 20</span>. */}
                    <div className="flex flex-col text-sm font-bold text-center text-[#ef4444] w-full">
                        <div>{t('note')}: {t('remark1')}</div>
                        <div>{t('remark2')}</div>
                    </div>
                </div>
            </div>
            <div>
                <QRCode 
                    value={tokenAddress} 
                    fgColor="#000000"
                />
            </div>
            <div className="text-base font-semibold text-center flex flex-col">
                <div>{t('wallet_address')}:</div>
                <div className=" font-bold flex items-center gap-1" >
                    <div>
                        {tokenAddress}
                    </div>
                    <div onClick={() => handleCopy(tokenAddress)}>
                        <Tooltip text={tooltipText}>
                            <CopyIcon />
                        </Tooltip>
                    </div>
                </div>
                {/* Wallet Address : {tokenAddress} */}
            </div>
            <form onSubmit={submit} className="flex flex-col justify-center items-center w-full gap-5">
                <div className="flex flex-col items-center gap-2 w-full md:max-w-[500px]">
                    <div className="flex items-center gap-3 w-full md:max-w-[500px]">
                        <div className="font-bold text-base">TxID: </div>
                        <Input 
                            id="txid" 
                            type='text'
                            value={data.txid}
                            handleChange={(e) => setData('txid', e.target.value)}
                            className="w-full"
                        />
                    </div>
                    <InputError message={returnErrors?.txid} className="text-base font-bold" />
                </div>
                {
                    returnErrors && (
                        <div>
                            {/* {
                                returnErrors.map((returnError) => (
                                    <div>
                                        {returnError}
                                    </div>
                                ))
                            } */}
                        </div>
                    )
                }
                <div>
                    <Button
                        size="lg"
                        className="w-40 flex justify-center items-center"
                        type="submit"
                        disabled={isLoading}
                    >
                        Submit
                    </Button>
                </div>
            </form>
            
        </div>
    )
}