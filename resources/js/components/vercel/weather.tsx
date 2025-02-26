import cx from 'classnames';
import { format, isWithinInterval } from 'date-fns';
import { useEffect, useState } from 'react';

interface WeatherAtLocation {
    latitude: number;
    longitude: number;
    generationtime_ms: number;
    utc_offset_seconds: number;
    timezone: string;
    timezone_abbreviation: string;
    elevation: number;
    current_units: {
        time: string;
        interval: string;
        temperature_2m: string;
    };
    current: {
        time: string;
        interval: number;
        temperature_2m: number;
    };
    hourly_units: {
        time: string;
        temperature_2m: string;
    };
    hourly: {
        time: string[];
        temperature_2m: number[];
    };
    daily_units: {
        time: string;
        sunrise: string;
        sunset: string;
    };
    daily: {
        time: string[];
        sunrise: string[];
        sunset: string[];
    };
}

function n(num: number): number {
    return Math.ceil(num);
}

export function Weather({ weatherAtLocation }: { weatherAtLocation: WeatherAtLocation }) {
    const currentHigh = Math.max(...weatherAtLocation.hourly.temperature_2m.slice(0, 24));
    const currentLow = Math.min(...weatherAtLocation.hourly.temperature_2m.slice(0, 24));

    const isDay = isWithinInterval(new Date(weatherAtLocation.current.time), {
        start: new Date(weatherAtLocation.daily.sunrise[0]),
        end: new Date(weatherAtLocation.daily.sunset[0]),
    });

    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const handleResize = () => {
            setIsMobile(window.innerWidth < 768);
        };

        handleResize();
        window.addEventListener('resize', handleResize);

        return () => window.removeEventListener('resize', handleResize);
    }, []);

    const hoursToShow = isMobile ? 5 : 6;

    // Find the index of the current time or the next closest time
    const currentTimeIndex = weatherAtLocation.hourly.time.findIndex((time) => new Date(time) >= new Date(weatherAtLocation.current.time));

    // Slice the arrays to get the desired number of items
    const displayTimes = weatherAtLocation.hourly.time.slice(currentTimeIndex, currentTimeIndex + hoursToShow);
    const displayTemperatures = weatherAtLocation.hourly.temperature_2m.slice(currentTimeIndex, currentTimeIndex + hoursToShow);

    return (
        <div
            className={cx(
                'skeleton-bg flex max-w-[500px] flex-col gap-4 rounded-2xl p-4',
                {
                    'bg-blue-400': isDay,
                },
                {
                    'bg-indigo-900': !isDay,
                },
            )}
        >
            <div className="flex flex-row items-center justify-between">
                <div className="flex flex-row items-center gap-2">
                    <div
                        className={cx(
                            'skeleton-div size-10 rounded-full',
                            {
                                'bg-yellow-300': isDay,
                            },
                            {
                                'bg-indigo-100': !isDay,
                            },
                        )}
                    />
                    <div className="text-4xl font-medium text-blue-50">
                        {n(weatherAtLocation.current.temperature_2m)}
                        {weatherAtLocation.current_units.temperature_2m}
                    </div>
                </div>

                <div className="text-blue-50">{`H:${n(currentHigh)}° L:${n(currentLow)}°`}</div>
            </div>

            <div className="flex flex-row justify-between">
                {displayTimes.map((time, index) => (
                    <div key={time} className="flex flex-col items-center gap-1">
                        <div className="text-xs text-blue-100">{format(new Date(time), 'ha')}</div>
                        <div
                            className={cx(
                                'skeleton-div size-6 rounded-full',
                                {
                                    'bg-yellow-300': isDay,
                                },
                                {
                                    'bg-indigo-200': !isDay,
                                },
                            )}
                        />
                        <div className="text-sm text-blue-50">
                            {n(displayTemperatures[index])}
                            {weatherAtLocation.hourly_units.temperature_2m}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
