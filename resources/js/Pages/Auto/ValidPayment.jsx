import Button from "@/Components/Button";
import Input from "@/Components/Input";
import InputError from "@/Components/InputError";
import Label from "@/Components/Label";
import { useForm } from "@inertiajs/react";
import React, { useState, useEffect } from "react";
import { QRCode } from 'react-qrcode-logo';
// import TronComponent from "@/Components/TronComponent";

export default function Payment({ merchant, transaction }) {
    const [currentWalletIndex, setCurrentWalletIndex] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [timeRemaining, setTimeRemaining] = useState(merchant.refresh_time);
    const [txid, setTxid] = useState();
    const [lastTimestamp, setLastTimestamp] = useState(0);
    const [blockTimestamp, setBlockTimestamp] = useState(0);
    const [transDetails, setLatestTransaction] = useState({});

    const { data, setData, post, processing, errors, reset } = useForm({
        txid: '', // Initial form state
        latestTransaction: {},
        transaction: transaction,
    });

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

    useEffect(() => {
        const fetchBlock = async () => {
            try {
                const response = await fetch('https://nile.trongrid.io/walletsolidity/getnowblock');
                const result = await response.json();
                const timestamp = result.block_header.raw_data.timestamp;
                setBlockTimestamp(timestamp);
            } catch (error) {
                console.error('Error fetching block:', error);
            }
        };

        const pollingInterval = setInterval(fetchBlock, 5000);
        return () => clearInterval(pollingInterval);
    }, []);

    useEffect(() => {
        const fetchTransactions = async () => {
            try {
                const url = `https://nile.trongrid.io/v1/accounts/${currentWallet.wallet_address.token_address}/transactions/trc20?contract_address=TXLAQ63Xg1NAzckPwKHvzw7CSEmLMEqcdj&order_by=block_timestamp,desc&min_timestamp=${blockTimestamp}`;
                const response = await fetch(url);
                const result = await response.json();
                console.log(result);
                if ((result.data != null) && (result.data.length === 1)) {
                    const latestTransaction = result.data[0];
                    setTxid(latestTransaction.transaction_id);
                    setLatestTransaction(latestTransaction);

                    setData('txid', latestTransaction.transaction_id);
                    setData('latestTransaction', latestTransaction);

                    if (data.latestTransaction.transaction_id) {
                        post('/updateTransaction', {
                            preserveScroll: true,
                            onSuccess: () => {
                                window.location.href = route('returnTransaction');
                            }
                        });
                    }
                }
            } catch (error) {
                console.error('Error fetching transactions:', error);
            }
        };

        const pollingInterval = setInterval(fetchTransactions, 4000); // Poll every 5 seconds

        return () => clearInterval(pollingInterval);
    }, [currentWallet, blockTimestamp, transDetails]);

    return (
        <div className="w-full flex flex-col items-center justify-center gap-5 min-h-screen">

            <div>
                <QRCode 
                value={currentWallet.wallet_address.token_address} 
                fgColor="#000000"
                />
            </div>
            <div className="text-base font-semibold">
                Wallet Address : {currentWallet.wallet_address.token_address}
            </div>
            <div className="text-base font-semibold">
                QR Code refreshing in: {timeRemaining} seconds
            </div>

            <div className="text-base" >
               TxID: <span className="font-bold" >{txid}</span>
            </div>

            
            <div>
                {/* <TronComponent /> */}
            </div>
        </div>
    );
}
