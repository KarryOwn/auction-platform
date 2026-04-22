window.listingFeePublish = ({
	previewUrl,
	walletBalance,
	modalName,
	formRef = 'publishForm',
}) => ({
	loading: false,
	fee: 0,
	walletBalance: Number(walletBalance || 0),
	async preview() {
		if (this.loading) {
			return;
		}

		this.loading = true;

		try {
			const response = await fetch(previewUrl, {
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			});

			const data = await response.json().catch(() => ({}));
			if (!response.ok) {
				throw new Error(data.message || 'Unable to load listing fee preview.');
			}

			this.fee = Number(data.listing_fee || 0);

			if (this.fee <= 0) {
				this.$refs[formRef]?.submit();
				return;
			}

			window.dispatchEvent(new CustomEvent('open-modal', { detail: modalName }));
		} catch (error) {
			window.toast?.error(error.message || 'Unable to load listing fee preview.');
		} finally {
			this.loading = false;
		}
	},
	confirmPublish() {
		window.dispatchEvent(new CustomEvent('close-modal', { detail: modalName }));
		this.$refs[formRef]?.submit();
	},
	canAfford() {
		return this.walletBalance >= this.fee;
	},
});
