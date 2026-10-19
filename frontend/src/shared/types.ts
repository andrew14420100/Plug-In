export type Theme = 'auto' | 'light' | 'dark';

export interface Favorite {
	id: string;
	label: string;
	icon: string;
}

export interface Preferences {
	theme: Theme;
	palette: string;
	hide_wp_menu: boolean;
	login_to_dashboard: boolean;
	widget_order: string[];
	hidden_widgets: string[];
	nav_order: string[];
	hidden_nav: string[];
	favorites: Favorite[];
}

export interface NavItem {
	id: string;
	label: string;
	url: string;
	icon: string;
}

export interface Candidate extends NavItem {}

export interface Widget {
	id: string;
	title: string;
	data: Record< string, unknown >;
}

export interface DashboardPayload {
	context: 'admin' | 'staff';
	prefs: Preferences;
	widgets: Widget[];
	nav: NavItem[];
	candidates: Candidate[];
	icons: string[];
	palettes: Record< string, string >;
	options: { allow_hide_wp_menu: boolean };
	links: { wp_dashboard: string; recovery: string };
	/** Logo ufficiale, presente solo nei pacchetti che lo includono. */
	brand_logo?: string;
}

export interface AuditItem {
	id: number;
	occurred_at: string;
	actor_id: number;
	actor_type: string;
	actor_label: string;
	action: string;
	module: string;
	resource_type: string;
	resource_id: string;
	diff: unknown;
	request_id: string;
}

export interface AuditPage {
	items: AuditItem[];
	total: number;
	page: number;
	per_page: number;
}
