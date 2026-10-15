import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import Icon from '../shared/Icon';
import { applyOrder, move, moveTo, toggle } from '../shared/list';
import type { Candidate, Preferences, Widget } from '../shared/types';
import Welcome from './widgets/Welcome';
import Favorites from './widgets/Favorites';
import Areas from './widgets/Areas';
import Modules from './widgets/Modules';
import Health from './widgets/Health';
import Activity from './widgets/Activity';

interface WidgetGridProps {
	widgets: Widget[];
	prefs: Preferences;
	candidates: Candidate[];
	editing: boolean;
	onChange: ( partial: Partial< Preferences > ) => void;
	onCustomize: () => void;
}

export default function WidgetGrid( {
	widgets,
	prefs,
	candidates,
	editing,
	onChange,
	onCustomize,
}: WidgetGridProps ) {
	const [ dragging, setDragging ] = useState< string | null >( null );
	const ordered = applyOrder( widgets, prefs.widget_order );
	const ids = ordered.map( ( w ) => w.id );
	const visible = editing
		? ordered
		: ordered.filter( ( w ) => ! prefs.hidden_widgets.includes( w.id ) );

	const render = ( widget: Widget ) => {
		const data = widget.data as never;
		switch ( widget.id ) {
			case 'welcome':
				return <Welcome data={ data } />;
			case 'favorites':
				return (
					<Favorites
						favorites={ prefs.favorites }
						candidates={ candidates }
						onCustomize={ onCustomize }
					/>
				);
			case 'areas':
				return <Areas data={ data } />;
			case 'modules':
				return <Modules data={ data } />;
			case 'health':
				return <Health data={ data } />;
			case 'activity':
				return <Activity data={ data } />;
		}
		return null;
	};

	if ( visible.length === 0 ) {
		return (
			<div className="bc-card bc-empty">
				<p>{ __( 'Hai nascosto tutti i widget.', 'bracelli-club' ) }</p>
				<button
					type="button"
					className="bc-button"
					onClick={ onCustomize }
				>
					{ __( 'Personalizza la dashboard', 'bracelli-club' ) }
				</button>
			</div>
		);
	}

	return (
		<div className={ `bc-widgets${ editing ? ' is-editing' : '' }` }>
			{ visible.map( ( widget, index ) => {
				const hidden = prefs.hidden_widgets.includes( widget.id );
				return (
					<section
						key={ widget.id }
						className={ `bc-card bc-widget bc-widget--${ widget.id }${ hidden ? ' is-hidden' : '' }${ dragging === widget.id ? ' is-dragging' : '' }` }
						aria-labelledby={ `bc-widget-${ widget.id }` }
						draggable={ editing }
						onDragStart={ ( event ) => {
							setDragging( widget.id );
							event.dataTransfer.effectAllowed = 'move';
							event.dataTransfer.setData(
								'text/plain',
								widget.id
							);
						} }
						onDragOver={ ( event ) => {
							if ( editing ) {
								event.preventDefault();
							}
						} }
						onDrop={ ( event ) => {
							event.preventDefault();
							const source =
								event.dataTransfer.getData( 'text/plain' );
							if ( source && source !== widget.id ) {
								onChange( {
									widget_order: moveTo(
										ids,
										source,
										widget.id
									),
								} );
							}
							setDragging( null );
						} }
						onDragEnd={ () => setDragging( null ) }
					>
						<header className="bc-widget-header">
							<h3 id={ `bc-widget-${ widget.id }` }>
								{ widget.title }
							</h3>
							{ editing && (
								<div className="bc-widget-tools">
									<span
										className="bc-drag-handle"
										aria-hidden="true"
										title={ __(
											'Trascina per spostare',
											'bracelli-club'
										) }
									>
										<Icon name="drag" size={ 18 } />
									</span>
									<button
										type="button"
										className="bc-icon-button"
										disabled={ index === 0 }
										onClick={ () =>
											onChange( {
												widget_order: move(
													ids,
													widget.id,
													-1
												),
											} )
										}
									>
										<Icon name="up" size={ 18 } />
										<span className="screen-reader-text">
											{ __(
												'Sposta prima',
												'bracelli-club'
											) }{ ' ' }
											— { widget.title }
										</span>
									</button>
									<button
										type="button"
										className="bc-icon-button"
										disabled={
											index === visible.length - 1
										}
										onClick={ () =>
											onChange( {
												widget_order: move(
													ids,
													widget.id,
													1
												),
											} )
										}
									>
										<Icon name="down" size={ 18 } />
										<span className="screen-reader-text">
											{ __(
												'Sposta dopo',
												'bracelli-club'
											) }{ ' ' }
											— { widget.title }
										</span>
									</button>
									<button
										type="button"
										className="bc-icon-button"
										aria-pressed={ ! hidden }
										onClick={ () =>
											onChange( {
												hidden_widgets: toggle(
													prefs.hidden_widgets,
													widget.id,
													! hidden
												),
											} )
										}
									>
										<Icon
											name={ hidden ? 'eyeOff' : 'eye' }
											size={ 18 }
										/>
										<span className="screen-reader-text">
											{ hidden
												? __(
														'Mostra widget',
														'bracelli-club'
													)
												: __(
														'Nascondi widget',
														'bracelli-club'
													) }{ ' ' }
											— { widget.title }
										</span>
									</button>
								</div>
							) }
						</header>
						<div className="bc-widget-body">
							{ render( widget ) }
						</div>
					</section>
				);
			} ) }
		</div>
	);
}
