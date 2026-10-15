import { __ } from '@wordpress/i18n';
import Icon from '../shared/Icon';
import { applyOrder } from '../shared/list';
import type { NavItem, Preferences } from '../shared/types';

interface SidebarProps {
	nav: NavItem[];
	prefs: Preferences;
	allowHideWpMenu: boolean;
	wpDashboard: string;
	onToggleWpMenu: () => void;
}

export default function Sidebar( {
	nav,
	prefs,
	allowHideWpMenu,
	wpDashboard,
	onToggleWpMenu,
}: SidebarProps ) {
	const items = applyOrder( nav, prefs.nav_order ).filter(
		( item ) =>
			item.id === 'dashboard' || ! prefs.hidden_nav.includes( item.id )
	);
	return (
		<nav
			className="bc-sidebar"
			aria-label={ __( 'Navigazione Bracelli', 'bracelli-club' ) }
		>
			<div className="bc-brand">
				<span className="bc-brand-mark" aria-hidden="true">
					B
				</span>
				<span className="bc-brand-name">
					Bracelli<span>Club</span>
				</span>
			</div>
			<ul className="bc-nav">
				{ items.map( ( item ) => (
					<li key={ item.id }>
						<a
							className={ `bc-nav-link${ item.id === 'dashboard' ? ' is-current' : '' }` }
							href={ item.url }
							aria-current={
								item.id === 'dashboard' ? 'page' : undefined
							}
						>
							<Icon name={ item.icon } />
							<span>{ item.label }</span>
						</a>
					</li>
				) ) }
			</ul>
			<div className="bc-sidebar-footer">
				{ allowHideWpMenu && (
					<button
						type="button"
						className="bc-nav-link bc-nav-button"
						onClick={ onToggleWpMenu }
					>
						<Icon name="wplogo" />
						<span>
							{ prefs.hide_wp_menu
								? __( 'Mostra menu WordPress', 'bracelli-club' )
								: __(
										'Nascondi menu WordPress',
										'bracelli-club'
									) }
						</span>
					</button>
				) }
				<a className="bc-nav-link" href={ wpDashboard }>
					<Icon name="home" />
					<span>{ __( 'Bacheca WordPress', 'bracelli-club' ) }</span>
				</a>
			</div>
		</nav>
	);
}
