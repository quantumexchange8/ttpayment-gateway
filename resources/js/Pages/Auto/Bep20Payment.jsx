import { useForm, usePage } from "@inertiajs/react";
import React, { useEffect, useRef, useState } from "react";
import { useTranslation } from 'react-i18next';
import { QRCode } from 'react-qrcode-logo';
import { CopyIcon } from "@/Components/Brand";
import Tooltip from "@/Components/Tooltip";
import Button from "@/Components/Button";
import { formatAmount } from "@/Composables";
import axios from "axios";

export default function Bep20Payment({ merchant, transaction, expirationTime, tokenAddress, storedToken, lang, referer, apikey, amount }) {

    const getRandomIndex = () => Math.floor(Math.random() * merchant.merchant_wallet_address.length);

    const [currentWalletIndex, setCurrentWalletIndex] = useState(getRandomIndex());
    const [isLoading, setIsLoading] = useState(false);
    const [timeRemaining, setTimeRemaining] = useState(merchant.refresh_time);
    const [txid, setTxid] = useState();
    const [lastTimestamp, setLastTimestamp] = useState(0);
    const [blockTimestamp, setBlockTimestamp] = useState(0);
    const [transDetails, setLatestTransaction] = useState({});
    const [expiredTimeRemainings, setExpiredTimeRemainings] = useState('');
    const [submitType, setSubmitType] = useState(false);
    const { t, i18n } = useTranslation();
    const [tooltipText, setTooltipText] = useState('copy');
    const isTransactionTurn = useRef(true);

    useEffect(() => {
            if (lang === 'en' || lang === 'cn' || lang === 'tw') {
              i18n.changeLanguage(lang);
            } else {
              i18n.changeLanguage('en');
            }

        }, [lang, i18n]);

    const { data, setData, post, processing, errors, reset } = useForm({
        txid: '', // Initial form state
        latestTransaction: {},
        transaction: transaction.id,
        merchantId: merchant.id,
        submitType: '',
        storedToken: storedToken,
        referer: referer
    });

    useEffect(() => {
        const refreshInterval = merchant.refresh_time * 1000; // Convert to milliseconds
        
        
        const updateWalletIndex = () => {
            const randomIndex = getRandomIndex();
            setCurrentWalletIndex(randomIndex);
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

    

    useEffect(() => {
            const fetchBlock = async () => {
                try {
                    const timestamp = Math.floor(Date.now() / 1000) - 15;
                    const response = await fetch(`https://api.bscscan.com/api?module=block&action=getblocknobytime&timestamp=${timestamp}&closest=before&apikey=${apikey}`);
                    // const response = await fetch(`https://api-testnet.bscscan.com/api?module=block&action=getblocknobytime&timestamp=${timestamp}&closest=before&apikey=${apikey}`);
                    const result = await response.json(); 

                    setBlockTimestamp(result.result)
                    
                } catch (error) {
                    console.error('Error fetching block:', error);
                }
            };
    
            const pollingInterval = setInterval(fetchBlock, 2000);
            return () => clearInterval(pollingInterval);
    }, []);

    const fetchTransactions = async () => {
        try {
            console.log("Fetching Transactions...");
            const url = `https://api.bscscan.com/api?module=account&action=txlist&address=${tokenAddress}&page=1&sort=asc&startblock=${blockTimestamp}&apikey=${apikey}`;
            // const url = `https://api-testnet.bscscan.com/api?module=account&action=txlist&address=${tokenAddress}&page=1&sort=asc&startblock=${blockTimestamp}&apikey=${apikey}`;

            const response = await fetch(url);
            const result = await response.json();

            console.log('result: ', result);

            if (result.result.length >= 1 && result.status === "1") {
                const latestTransaction = result.result[0];
                setTxid(latestTransaction.hash);
                setLatestTransaction(latestTransaction);

                setData('txid', latestTransaction.hash);
                setData('latestTransaction', latestTransaction);

                console.log('latestTransaction Transaction', data.latestTransaction)

                // if (data.latestTransaction.hash) {
                const response = await axios.post('/updateTransaction', {
                    latestTransaction: latestTransaction,
                    transaction: transaction.id,
                    merchantId: merchant.id,
                    submitType: '',
                    storedToken: storedToken,
                    referer: referer,
                });

                if (response.status === 200) {
                    window.location.href = `/returnTransaction?transaction_id=${transaction.id}&token=${storedToken}&merchant_id=${merchant.id}&referer=${referer}`;
                }
                    
                    // clearInterval(pollingInterval);
                // }
            }
        } catch (error) {
            console.error('Error fetching transactions:', error);
        }
    };

    const fetchTransfer = async () => {
        try {
            console.log("Fetching Transfer...");

            const url = `https://api.bscscan.com/api?module=account&action=tokentx&address=${tokenAddress}&page=1&sort=asc&startblock=${blockTimestamp}&apikey=${apikey}`;
            // const url = `https://api-testnet.bscscan.com/api?module=account&action=tokentx&address=${tokenAddress}&page=1&sort=asc&startblock=${blockTimestamp}&apikey=${apikey}`;

            const response = await fetch(url);
            const result = await response.json();

            console.log('result: ', result);

            if (result.result.length >= 1 && result.status === "1") {
                const latestTransaction = result.result[0];
                setTxid(latestTransaction.hash);
                setLatestTransaction(latestTransaction);

                setData('txid', latestTransaction.hash);
                setData('latestTransaction', latestTransaction);

                console.log('latestTransaction Transfer', data.latestTransaction)

                // if (data.latestTransaction.hash) {

                const response = await axios.post('/updateTransaction', {
                    latestTransaction: latestTransaction,
                    transaction: transaction.id,
                    merchantId: merchant.id,
                    submitType: '',
                    storedToken: storedToken,
                    referer: referer,
                });

                if (response.status === 200) {
                    window.location.href = `/returnTransaction?transaction_id=${transaction.id}&token=${storedToken}&merchant_id=${merchant.id}&referer=${referer}`;
                }
                    // post('/updateTransaction', {
                    //     preserveScroll: true,
                    //     onSuccess: () => {
                    //         window.location.href = `/returnTransaction?transaction_id=${transaction.id}&token=${storedToken}&merchant_id=${merchant.id}&referer=${referer}`;
                    //     }
                    // });
                    // clearInterval(pollingInterval);
                // }
            }

        } catch (error) {
            console.error('Error fetching Transfer:', error);
        }
    }
    
    useEffect(() => {
        // let pollingInterval;
        if (!blockTimestamp) return;

        const pollingInterval = setInterval(() => {
            if (isTransactionTurn.current) {
                fetchTransactions();
            } else {
                fetchTransfer();
            }
            isTransactionTurn.current = !isTransactionTurn.current; // Toggle between transactions and transfers
        }, 3000);

        return () => clearInterval(pollingInterval);

    }, [blockTimestamp]);

    // console.log(currentWallet.wallet_address.token_address)
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
                window.location.href = `/sessionTimeOut?transaction_id=${transaction.id}`;
            }
        };

        calculateTimeRemaining(); // Initial call to set time remaining immediately

        const intervalId = setInterval(calculateTimeRemaining, 1000); // Update every second

        return () => clearInterval(intervalId); // Cleanup interval on component unmount
    }, [expirationTime]);

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
                        {t('please_ensure')}<span className="font-bold">BEP 20</span>.
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
            <div className="text-gray-900 font-bold text-xxl">
                ${formatAmount(amount)}
            </div>
            <div className="text-base font-semibold text-center flex flex-col max-w-80 md:max-w-full">
                <div>{t('wallet_address')}:</div> 
                <div className=" font-bold flex items-center gap-1" >
                    <div className="break-all">
                        {tokenAddress}
                    </div>
                    <div onClick={() => handleCopy(tokenAddress)}>
                        <Tooltip text={tooltipText}>
                            <CopyIcon />
                        </Tooltip>
                    </div>
                </div>
            </div>

           
            <Button
                type="submit"
                variant="black"
                size="sm"
                className="bg-[#525252] hover:bg-[#404040] cursor-not-allowed"
            >
                <span className="text-base font-semibold">
                    {t('time_remaining')}: {expiredTimeRemainings}
                </span>
            </Button>
        </div>
    )
}