/**
 * Logo ufficiale se il pacchetto lo fornisce (window.bracelliDashboard.brand_logo), altrimenti il marchio testuale.
 * @param root0
 * @param root0.wordmark
 */
export default function Brand( { wordmark }: { wordmark: JSX.Element } ) {
	const logo = window.bracelliDashboard?.brand_logo;
	if ( ! logo ) {
		return wordmark;
	}
	return (
		<img
			className="bc-official-logo"
			src={ logo }
			alt="Bracelli Club — 35° anniversario"
		/>
	);
}
