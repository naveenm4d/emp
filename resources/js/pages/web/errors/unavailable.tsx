import { ErrorMessage } from './message';

export default function Unavailable() {
    return (
        <ErrorMessage title="We couldn't load this invitation">
            Something went wrong on our side. Please try again in a minute.
        </ErrorMessage>
    );
}
