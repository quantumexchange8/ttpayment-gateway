import Button from "@/Components/Button";
import Input from "@/Components/Input";
import InputError from "@/Components/InputError";
import Label from "@/Components/Label";
import { useForm } from "@inertiajs/react";
import React, { useState, useEffect } from "react";
import { QRCode } from 'react-qrcode-logo';
// import TronComponent from "@/Components/TronComponent";
// import toast from 'react-hot-toast';

export default function Payment({ merchant, merchantClientId, vCode, orderNumber, expirationTime, transaction }) {
    const [currentWalletIndex, setCurrentWalletIndex] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [timeRemaining, setTimeRemaining] = useState(merchant.refresh_time);
    const [expiredTimeRemainings, setExpiredTimeRemainings] = useState('');
    const [txidVal, setTxidVal ] = useState();
    const [txidError, setTxidError] = useState(null);

    useEffect(() => {
        const refreshInterval = merchant.refresh_time * 1000; // Convert to milliseconds
        
        
        const updateWalletIndex = () => {
            setCurrentWalletIndex(prevIndex => (prevIndex + 1) % merchant.merchant_wallet_address.length);
        };

        const interval = setInterval(() => {
            setTimeRemaining(prevTime => {
                if (prevTime <= 1) {
                    updateWalletIndex();
                    return merchant.refresh_time;
                }
                return prevTime - 1;
            });
        }, 1000);
        
        return () => clearInterval(interval);
    }, [merchant.refresh_time, merchant.merchant_wallet_address.length]);

    const currentWallet = merchant.merchant_wallet_address[currentWalletIndex];

    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        txid: '',
        receipt: '',
        merchantId: merchant.id,
        to_wallet: currentWallet.wallet_address.token_address,
        merchantClientId: merchantClientId,
        vCode: vCode,
        orderNumber: orderNumber,
        txidValue: txidVal,
        transaction: transaction.id,
    })

    const returnCall = () => {
        post('/returnSession', {
            preserveScroll: true,
            onSuccess: () => {
                setIsLoading(false);
            }
        })
    }

    useEffect(() => {
        const findTxID = async () => {
            if (!data.txid) return;

            try {
                // const url = `https://nile.trongrid.io/v1/transactions/${data.txid}/events`;
                const url = `https://apilist.tronscanapi.com/api/transaction-info?hash=${data.txid}`;
                const response = await fetch(url);
                const result = await response.json();

                if (result.trc20TransferInfo && result.trc20TransferInfo.length > 0) {
                    // console.log(result.trc20TransferInfo[0])
                    setTxidVal(result.trc20TransferInfo[0]);
                    setTxidError(null);

                    setData({
                        ...data,
                        amountVal: result.trc20TransferInfo[0].amount_str,
                        contractAddress: result.trc20TransferInfo[0].contract_address,
                        decimals: result.trc20TransferInfo[0].decimals,
                        fromAddress: result.trc20TransferInfo[0].from_address,
                        toAddress: result.trc20TransferInfo[0].to_address,
                        timeStamp: result.timestamp,
                    });

                } else {
                    setTxidError('Please enter a valid txid');
                    setTxidVal(null);
                }
                
            } catch (error) {
                console.error('Error fetching transactions:', error);
                setTxidError('Error fetching transactions, please try again');
                setTxidVal(null);
            }
        }
        findTxID();

    }, [data.txid]);

    const submit = (e) => {

        e.preventDefault();
        setIsLoading(true);
        if (txidError) {
            setIsLoading(false);
            return
        };
        post('/updateTransaction', {
            preserveScroll: true,
            onSuccess: () => {
                setIsLoading(false);
            }
        })
    }

    useEffect(() => {
        const calculateTimeRemaining = () => {
            const now = new Date();
            const expirationDate = new Date(expirationTime);
            const timeDiff = expirationDate - now;

            if (timeDiff > 0) {
                const minutes = Math.floor((timeDiff / 1000 / 60) % 60);
                const seconds = Math.floor((timeDiff / 1000) % 60);
                setExpiredTimeRemainings(`${minutes}m ${seconds}s`);
            } else {
                
                setExpiredTimeRemainings('Expired');
                window.location.href = '/sessionTimeOut';
            }
        };

        calculateTimeRemaining(); // Initial call to set time remaining immediately

        const intervalId = setInterval(calculateTimeRemaining, 1000); // Update every second

        return () => clearInterval(intervalId); // Cleanup interval on component unmount
    }, [expirationTime]);

    return (
        <div className="w-full flex flex-col items-center justify-center gap-5 min-h-screen">

            <div>
                <QRCode 
                value={currentWallet.wallet_address.token_address} 
                fgColor="#000000"
                />
            </div>
            <div className="text-base font-semibold text-center">
                Wallet Address : {currentWallet.wallet_address.token_address}
            </div>
            <div className="text-base font-semibold">
                QR Code refreshing in: {timeRemaining} seconds
            </div>

            {
                merchant.deposit_type == 0 && (
                    <form onSubmit={submit}>
                        <div className=" w-96 flex flex-col gap-3">
                            <div className="space-y-1.5">
                                <div className='flex items-center gap-1'>
                                    <Label for="amount" value="Amount"/> <span className='text-sm text-error-600 font-medium'>*</span>
                                </div>
                                <Input 
                                    id="amount" 
                                    type='number'
                                    value={data.amount}
                                    handleChange={(e) => setData('amount', e.target.value)}
                                    required
                                    className="w-full"
                                />
                                <InputError message={errors.amount}/>
                            </div>
                            <div className="space-y-1.5">
                                <div className='flex items-center gap-1'>
                                    <Label for="txid" value="Txid"/> <span className='text-sm text-error-600 font-medium'>*</span>
                                </div>
                                <Input 
                                    id="txid" 
                                    type='text'
                                    value={data.txid}
                                    handleChange={(e) => setData('txid', e.target.value)}
                                    required
                                    className="w-full"
                                />
                                {txidError && (
                                    <div className="text-red-500">
                                        {txidError}
                                    </div>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <div className='flex items-center gap-1'>
                                    <Label for="receipt" value="Upload Receipt"/> <span className='text-sm text-error-600 font-medium'>*</span>
                                </div>
                                <Input 
                                    id="receipt" 
                                    type='file'
                                    value={data.receipt}
                                    handleChange={(e) => setData('receipt', e.target.value)}
                                    // required
                                    className="w-full"
                                />
                                <InputError message={errors.receipt}/>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    size='lg'
                                    variant="danger"
                                    className='w-full flex justify-center'
                                    disabled={isLoading}
                                    onClick={returnCall}
                                >
                                    {isLoading ? ( // Show loading indicator if isLoading is true
                                    <l-dot-pulse size="43" speed="1.3" color="white" />
                                    ) : (
                                    'Cancel'
                                    )}
                                </Button>
                                <Button
                                    type="submit"
                                    size='lg'
                                    className='w-full flex justify-center'
                                    disabled={isLoading}
                                >
                                    {isLoading ? ( // Show loading indicator if isLoading is true
                                    <l-dot-pulse size="43" speed="1.3" color="white" />
                                    ) : (
                                    'Submit'
                                    )}
                                </Button>
                            </div>
                        </div>
                    </form>
                    
                )
            }

            <div className="text-base font-semibold">
                Time Remaing: {expiredTimeRemainings}
            </div>
            
            <div>
                {/* <TronComponent /> */}
            </div>
        </div>
    );
}
