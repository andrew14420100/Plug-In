import { __ } from '@wordpress/i18n';
import Icon from '../shared/Icon';
import type { Preferences, Theme } from '../shared/types';

interface TopbarProps {
	name: string;
	context: 'admin' | 'staff';
	today: string;
	prefs: Preferences;
	palettes: Record< string, string >;
	editing: boolean;
	status: 'idle' | 'saving' | 'saved' | 'error';
	onTheme: ( theme: Theme ) => void;
	onPalette: ( palette: string ) => void;
	onToggleEdit: () => void;
}

const THEMES: Array< { id: Theme; icon: string; label: string } > = [
	{ id: 'auto', icon: 'monitor', label: __( 'Automatico', 'bracelli-club' ) },
	{ id: 'light', icon: 'sun', label: __( 'Chiaro', 'bracelli-club' ) },
	{ id: 'dark', icon: 'moon', label: __( 'Scuro', 'bracelli-club' ) },
];

export default function Topbar( {
	name,
	context,
	today,
	prefs,
	palettes,
	editing,
	status,
	onTheme,
	onPalette,
	onToggleEdit,
}: TopbarProps ) {
	return (
		<header className="bc-header bc-topbar">
			<div>
				<p className="bc-eyebrow">
					Bracelli Club ·{ ' ' }
					{ context === 'admin'
						? __( 'Area amministratore', 'bracelli-club' )
						: __( 'Area personale', 'bracelli-club' ) }
				</p>
				<h2 className="bc-title">
					{ __( 'Ciao,', 'bracelli-club' ) } { name }
				</h2>
				<p className="bc-subtitle">{ today }</p>
			</div>
			<div className="bc-toolbar">
				<span
					className={ `bc-save-status is-${ status }` }
					role="status"
					aria-live="polite"
				>
					{ status === 'saving' &&
						__( 'Salvataggio…', 'bracelli-club' ) }
					{ status === 'saved' &&
						__( 'Preferenze salvate', 'bracelli-club' ) }
					{ status === 'error' &&
						__( 'Salvataggio non riuscito', 'bracelli-club' ) }
				</span>
				<div
					className="bc-segmented"
					role="radiogroup"
					aria-label={ __( 'Tema', 'bracelli-club' ) }
				>
					{ THEMES.map( ( theme ) => (
						<button
							key={ theme.id }
							type="button"
							role="radio"
							aria-checked={ prefs.theme === theme.id }
							className={
								prefs.theme === theme.id ? 'is-active' : ''
							}
							onClick={ () => onTheme( theme.id ) }
							title={ theme.label }
						>
							<Icon name={ theme.icon } size={ 18 } />
							<span className="screen-reader-text">
								{ theme.label }
							</span>
						</button>
					) ) }
				</div>
				<div
					className="bc-swatches"
					role="radiogroup"
					aria-label={ __( 'Tavolozza', 'bracelli-club' ) }
				>
					{ Object.entries( palettes ).map( ( [ id, label ] ) => (
						<button
							key={ id }
							type="button"
							role="radio"
							aria-checked={ prefs.palette === id }
							className={ `bc-swatch bc-swatch--${ id }${ prefs.palette === id ? ' is-active' : '' }` }
							onClick={ () => onPalette( id ) }
							title={ label }
						>
							<span className="screen-reader-text">
								{ label }
							</span>
						</button>
					) ) }
				</div>
				<button
					type="button"
					className={ `bc-button${ editing ? ' bc-button--primary' : '' }` }
					aria-pressed={ editing }
					onClick={ onToggleEdit }
				>
					<Icon name="sliders" size={ 18 } />
					{ editing
						? __( 'Fine personalizzazione', 'bracelli-club' )
						: __( 'Personalizza', 'bracelli-club' ) }
				</button>
			</div>
		</header>
	);
}
