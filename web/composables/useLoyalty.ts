/** Add-on «الولاء والمحفظة»: labels shared by the loyalty pages. */
export const loyaltyType: Record<string, { label: string; cls: string; icon: string }> = {
  get earn() { return { label: tt('نقاط مكتسبة', 'Earned'), cls: 'ok', icon: 'ti-circle-plus' } },
  get redeem() { return { label: tt('استبدال نقاط', 'Redeemed'), cls: 'info', icon: 'ti-gift' } },
  get adjust() { return { label: tt('تعديل يدوي', 'Adjustment'), cls: 'warn', icon: 'ti-adjustments' } },
  get topup() { return { label: tt('شحن محفظة', 'Wallet top-up'), cls: 'ok', icon: 'ti-wallet' } },
  get spend() { return { label: tt('دفع من المحفظة', 'Paid from wallet'), cls: 'info', icon: 'ti-shopping-cart' } },
  get refund() { return { label: tt('استرداد رصيد', 'Refund'), cls: 'bad', icon: 'ti-arrow-back-up' } },
  get reversal() { return { label: tt('إلغاء (عكس)', 'Reversal'), cls: 'muted', icon: 'ti-rotate' } },
}
