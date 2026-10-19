/** Shared session state; requests are serialized and responses belong to one user only. */
export function createOnboardingStore(send, changed) {
    let identity = null;
    let shared;
    let state;
    let epoch = 0;
    let queue = Promise.resolve();
    let pending = 0;

    function sync(user, value) {
        if (user !== identity) {
            identity = user;
            epoch += 1;
            pending = 0;
            queue = Promise.resolve();
            shared = undefined;
            state = undefined;
        }

        if (value !== shared) {
            shared = value;

            if (
                !pending &&
                value &&
                (!state || value.revision >= state.revision)
            ) {
                state = value;
            }
        }

        changed(state);
    }

    function update(body) {
        if (!identity) {
            return Promise.resolve(false);
        }

        const owner = identity;
        const version = epoch;
        pending += 1;
        const result = queue.then(async () => {
            if (!owner || version !== epoch) {
                return false;
            }

            try {
                const value = await send(body);

                if (version !== epoch) {
                    return false;
                }

                state =
                    shared && shared.revision > value.revision ? shared : value;
                changed(state);

                return true;
            } catch {
                return false;
            } finally {
                if (version === epoch) {
                    pending -= 1;
                }
            }
        });
        queue = result.then(() => undefined);

        return result;
    }

    return { sync, update };
}
