import { ErrorMessage } from './message';

export default function NotFound() {
    return (
        <ErrorMessage title="Page not found">
            This link doesn't match any invitation or event. Please check that
            you opened the full link you received, or ask the host to send it
            again.
        </ErrorMessage>
    );
}
